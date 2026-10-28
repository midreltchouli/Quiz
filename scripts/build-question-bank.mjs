import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { setTimeout as delay } from 'node:timers/promises';

// Explicitly run this script to refresh the checked-in, offline question bank.
// Wikidata structured data is CC0: https://www.wikidata.org/wiki/Wikidata:Licensing
const languages = ['en', 'fr', 'de'];
const bank = [];
const seen = new Set();
const cachePath = 'storage/app/quiz-import';
await mkdir(cachePath, { recursive: true });
const labelCache = new Map(await readFile(`${cachePath}/labels.json`, 'utf8').then(JSON.parse).catch(() => []));
// Wikidata uses "mul" for names that have the same spelling in all languages.
async function query(sparql) {
    const file = `${cachePath}/${createHash('sha256').update(sparql).digest('hex')}.json`;
    const saved = await readFile(file, 'utf8').then(JSON.parse).catch(() => null);
    if (saved) return saved;
    for (let attempt = 0; attempt < 4; attempt++) {
        try {
            const response = await fetch('https://query.wikidata.org/sparql?format=json&query=' + encodeURIComponent(sparql), {
                headers: { 'User-Agent': 'QuizEducationalBank/1.0', Accept: 'application/sparql-results+json' },
                signal: AbortSignal.timeout(55000),
            });
            if (!response.ok) throw new Error(`Wikidata HTTP ${response.status}`);
            const rows = (await response.json()).results.bindings;
            await writeFile(file, JSON.stringify(rows));
            return rows;
        } catch (error) {
            if (attempt === 3) throw error;
            console.log(`Retry: ${error.message}`);
            await delay(3000 * (attempt + 1));
        }
    }
}

const id = uri => uri.split('/').pop();
const normalize = text => text.normalize('NFKC').trim().toLocaleLowerCase();

async function getLabels(uris) {
    const missing = [...new Set(uris.map(id))].filter(key => !labelCache.has(key));
    for (let offset = 0; offset < missing.length; offset += 50) {
        const chunk = missing.slice(offset, offset + 50);
        const url = new URL('https://www.wikidata.org/w/api.php');
        url.search = new URLSearchParams({ action: 'wbgetentities', ids: chunk.join('|'), props: 'labels', languages: [...languages, 'mul'].join('|'), format: 'json' });
        let data;
        for (let attempt = 0; attempt < 6; attempt++) {
            const response = await fetch(url, { headers: { 'User-Agent': 'QuizEducationalBank/1.0' }, signal: AbortSignal.timeout(45000) });
            if (response.ok) { data = await response.json(); break; }
            if (response.status !== 429 && response.status !== 503) throw new Error(`Label download HTTP ${response.status}`);
            console.log(`Label service busy; retry ${attempt + 1}`);
            await delay(Math.max(10000, Number(response.headers.get('retry-after') || 0) * 1000));
        }
        if (!data) throw new Error('Label service unavailable after retries. Rerun to resume from cache.');
        for (const key of chunk) {
            const values = data.entities?.[key]?.labels;
            labelCache.set(key, languages.every(lang => (values?.[lang] ?? values?.mul)?.value) ? Object.fromEntries(languages.map(lang => [lang, (values[lang] ?? values.mul).value.trim()])) : null);
        }
        await writeFile(`${cachePath}/labels.json`, JSON.stringify([...labelCache]));
        await delay(1500);
    }
}

function addFacts(category, facts, templates, limit) {
    let added = 0;
    const uniqueFacts = facts.filter((fact, index, all) => all.findIndex(other => other.key === fact.key) === index);
    const answerSets = new Map();
    for (const fact of uniqueFacts) for (const lang of languages) {
        const key = `${lang}:${normalize(fact.subject[lang])}`;
        const values = answerSets.get(key) ?? new Set();
        values.add(normalize(fact.answer[lang]));
        answerSets.set(key, values);
    }
    for (const fact of uniqueFacts) {
        if (added >= limit) break;
        if (languages.some(lang => answerSets.get(`${lang}:${normalize(fact.subject[lang])}`).size > 1)) continue;
        const options = [fact.answer];
        // Deterministic distractors, with distinct labels in every language.
        const offset = Number(fact.key.replace(/\D/g, '').slice(-6)) || 0;
        for (let index = 0; index < uniqueFacts.length && options.length < 4; index++) {
            const candidate = uniqueFacts[(offset + index * 17) % uniqueFacts.length].answer;
            if (languages.every(lang => options.every(option => normalize(option[lang]) !== normalize(candidate[lang])))) options.push(candidate);
        }
        if (options.length !== 4) continue;
        const position = offset % 4;
        [options[0], options[position]] = [options[position], options[0]];
        const translations = Object.fromEntries(languages.map(lang => [lang, {
            question: templates[lang](fact.subject[lang]),
            options: options.map(option => option[lang]),
            answer: fact.answer[lang],
        }]));
        if (languages.some(lang => translations[lang].question.length > 255 || seen.has(`${lang}:${normalize(translations[lang].question)}`))) continue;
        for (const lang of languages) seen.add(`${lang}:${normalize(translations[lang].question)}`);
        bank.push({ key: `${category}:${fact.key}`, category, source_url: `https://www.wikidata.org/wiki/${fact.key}`, translations });
        added++;
    }
    console.log(`${category}: ${added} questions (${bank.length} total)`);
}

async function entityFacts(category, pattern, templates, limit) {
    const rows = await query(`SELECT DISTINCT ?item ?answer ?links WHERE {
            ${pattern}
            BIND(?value AS ?answer)
            ?item wikibase:sitelinks ?links. FILTER(?links >= 30)
    } ORDER BY DESC(?links) STR(?item) LIMIT ${limit * 3}`);
    console.log(`${category}: ${rows.length} source facts downloaded`);
    await getLabels(rows.flatMap(row => [row.item.value, row.answer.value]));
    const answers = new Map();
    for (const row of rows) {
        const values = answers.get(row.item.value) ?? new Set();
        values.add(row.answer.value);
        answers.set(row.item.value, values);
    }
    addFacts(category, rows.filter(row => answers.get(row.item.value).size === 1 && labelCache.get(id(row.item.value)) && labelCache.get(id(row.answer.value))).map(row => ({ key: id(row.item.value), subject: labelCache.get(id(row.item.value)), answer: labelCache.get(id(row.answer.value)) })), templates, limit);
}

await entityFacts('capitals', '?item wdt:P31 wd:Q3624078; wdt:P36 ?value.', {
    en: name => `What is the capital of ${name}?`,
    fr: name => `Quelle est la capitale du pays suivant : ${name} ?`,
    de: name => `Wie heißt die Hauptstadt von ${name}?`,
}, 180);

await entityFacts('cities', '?item wdt:P31/wdt:P279* wd:Q515; wdt:P17 ?value.', {
    en: name => `In which country is the city of ${name}?`,
    fr: name => `Dans quel pays se trouve la ville suivante : ${name} ?`,
    de: name => `In welchem Land liegt die Stadt ${name}?`,
}, 350);

const elements = await query(`SELECT ?item ?number ?symbol WHERE {
    ?item wdt:P31 wd:Q11344; wdt:P1086 ?number; wdt:P246 ?symbol.
} ORDER BY ?number`);
await getLabels(elements.map(row => row.item.value));
for (const [category, property, templates] of [
    ['chemical-symbols', 'symbol', {
        en: name => `What is the chemical symbol for the element ${name}?`,
        fr: name => `Quel est le symbole chimique de cet élément : ${name} ?`,
        de: name => `Welches chemische Symbol hat das Element ${name}?`,
    }],
    ['atomic-numbers', 'number', {
        en: name => `What is the atomic number of the element ${name}?`,
        fr: name => `Quel est le numéro atomique de cet élément : ${name} ?`,
        de: name => `Welche Ordnungszahl hat das Element ${name}?`,
    }],
]) {
    addFacts(category, elements.filter(row => labelCache.get(id(row.item.value))).map(row => ({ key: id(row.item.value), subject: labelCache.get(id(row.item.value)), answer: Object.fromEntries(languages.map(lang => [lang, row[property].value])) })), templates, 118);
}

addFacts('elements-from-symbols', elements.filter(row => labelCache.get(id(row.item.value))).map(row => ({
    key: id(row.item.value),
    subject: Object.fromEntries(languages.map(lang => [lang, row.symbol.value])),
    answer: labelCache.get(id(row.item.value)),
})), {
    en: symbol => `Which chemical element has the symbol ${symbol}?`,
    fr: symbol => `Quel élément chimique a pour symbole ${symbol} ?`,
    de: symbol => `Welches chemische Element hat das Symbol ${symbol}?`,
}, 118);

await entityFacts('literature', '?item wdt:P31/wdt:P279* wd:Q7725634; wdt:P50 ?value.', {
    en: name => `Who is the author of the literary work “${name}”?`,
    fr: name => `Qui est l’auteur de l’œuvre littéraire « ${name} » ?`,
    de: name => `Wer hat das literarische Werk „${name}“ geschrieben?`,
}, 300);

await entityFacts('painting', 'VALUES ?value { wd:Q5593 wd:Q5582 wd:Q762 wd:Q5580 wd:Q5589 wd:Q296 wd:Q5588 wd:Q5599 wd:Q5577 wd:Q5432 wd:Q5598 wd:Q5581 } ?item wdt:P31 wd:Q3305213; wdt:P170 ?value.', {
    en: name => `Who painted “${name}”?`,
    fr: name => `Qui a peint le tableau « ${name} » ?`,
    de: name => `Wer hat das Gemälde „${name}“ gemalt?`,
}, 300);

if (bank.length < 1000) throw new Error(`Only ${bank.length} complete multilingual questions; output was not replaced.`);
await mkdir('database/seeders/data', { recursive: true });
await writeFile('database/seeders/data/questions.json', JSON.stringify({
    version: 1,
    source: 'Wikidata',
    license: 'CC0-1.0',
    license_url: 'https://creativecommons.org/publicdomain/zero/1.0/',
    retrieved_at: new Date().toISOString(),
    languages,
    questions: bank,
}, null, 2) + '\n');
console.log(`Saved ${bank.length} questions, each available in all three languages.`);
