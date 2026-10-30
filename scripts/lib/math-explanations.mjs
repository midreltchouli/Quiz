const gcd = (a, b) => b ? gcd(b, a % b) : a;
const fraction = (a, b) => `${a / gcd(a, b)}/${b / gcd(a, b)}`;

// Plain text is deliberate: formulas remain readable offline, without an external renderer.
// The returned order is English, French, German, matching the challenge bank.
export function mathExplanations(key) {
    const value = Number(key.slice(key.lastIndexOf('-') + 1));
    if (!Number.isInteger(value)) throw new Error(`Invalid math key: ${key}`);

    if (key.startsWith('combinations-')) {
        const n = value;
        const ordered = n * (n - 1) * (n - 2);
        const result = ordered / 6;
        return [
            `Formula: C(n,k) = n! / (k! × (n−k)!). The symbol ! means factorial: for example, 3! = 3 × 2 × 1 = 6.\nHere n = ${n} people and k = 3 people per group. We first count ordered selections: ${n} choices, then ${n - 1}, then ${n - 2}. Each group is counted 3! = 6 times because its three members can be ordered in six ways. Since order does not matter, divide by 6.\nCalculation: C(${n},3) = (${n} × ${n - 1} × ${n - 2}) / (3 × 2 × 1) = ${ordered}/6 = ${result} groups.`,
            `Formule : C(n,k) = n! / (k! × (n−k)!). Le symbole ! désigne la factorielle : par exemple, 3! = 3 × 2 × 1 = 6.\nIci, n = ${n} personnes et k = 3 personnes par groupe. On compte d’abord les choix ordonnés : ${n} possibilités, puis ${n - 1}, puis ${n - 2}. Chaque groupe est compté 3! = 6 fois, car ses trois membres peuvent être ordonnés de six façons. Puisque l’ordre ne compte pas, on divise par 6.\nApplication : C(${n},3) = (${n} × ${n - 1} × ${n - 2}) / (3 × 2 × 1) = ${ordered}/6 = ${result} groupes.`,
            `Formel: C(n,k) = n! / (k! × (n−k)!). Das Zeichen ! bedeutet Fakultät: zum Beispiel 3! = 3 × 2 × 1 = 6.\nHier sind n = ${n} Personen verfügbar und k = 3 Personen bilden eine Gruppe. Zunächst zählt man geordnete Auswahlen: ${n} Möglichkeiten, dann ${n - 1}, dann ${n - 2}. Jede Gruppe wird 3! = 6 Mal gezählt, weil ihre drei Mitglieder auf sechs Arten angeordnet werden können. Da die Reihenfolge egal ist, teilt man durch 6.\nRechnung: C(${n},3) = (${n} × ${n - 1} × ${n - 2}) / (3 × 2 × 1) = ${ordered}/6 = ${result} Gruppen.`,
        ];
    }

    if (key.startsWith('dice-')) {
        const pairs = Array.from({length: 6}, (_, i) => [i + 1, value - i - 1]).filter(([, b]) => b >= 1 && b <= 6);
        const list = pairs.map(([a,b]) => `(${a},${b})`).join(', ');
        const result = fraction(pairs.length, 36);
        return [
            `Formula: P(E) = number of favourable outcomes / total number of outcomes, when all outcomes are equally likely. E is the event “the sum is ${value}”.\nTwo independent fair dice give 6 × 6 = 36 ordered pairs. The first entry is the first die, so (1,6) and (6,1) are different outcomes. The favourable pairs are: ${list}. There are ${pairs.length}.\nCalculation: P(E) = ${pairs.length}/36 = ${result}. Reduce a fraction by dividing its numerator and denominator by their greatest common divisor.`,
            `Formule : P(E) = nombre d’issues favorables / nombre total d’issues, lorsque les issues sont équiprobables. E est l’événement « la somme vaut ${value} ».\nDeux dés équilibrés et indépendants donnent 6 × 6 = 36 couples ordonnés. La première valeur est celle du premier dé : (1,6) et (6,1) sont donc deux issues différentes. Les couples favorables sont : ${list}. Il y en a ${pairs.length}.\nApplication : P(E) = ${pairs.length}/36 = ${result}. On simplifie une fraction en divisant le numérateur et le dénominateur par leur plus grand diviseur commun.`,
            `Formel: P(E) = Anzahl günstiger Ergebnisse / Anzahl aller Ergebnisse, wenn alle Ergebnisse gleich wahrscheinlich sind. E bedeutet „die Summe ist ${value}“.\nZwei unabhängige faire Würfel ergeben 6 × 6 = 36 geordnete Paare. Der erste Eintrag gehört zum ersten Würfel: (1,6) und (6,1) sind daher verschiedene Ergebnisse. Die günstigen Paare sind: ${list}. Es sind ${pairs.length}.\nRechnung: P(E) = ${pairs.length}/36 = ${result}. Man kürzt einen Bruch, indem man Zähler und Nenner durch ihren größten gemeinsamen Teiler teilt.`,
        ];
    }

    if (key.startsWith('derivative-')) {
        const n = value;
        const power = 2 ** (n - 1);
        const result = n * power;
        return [
            `Formula: if f(x) = x^n, the power rule gives f′(x) = n × x^(n−1). The derivative describes the instantaneous rate of change of f.\nHere n = ${n}. Multiply by the exponent and reduce that exponent by 1: f′(x) = ${n} × x^${n - 1}. Only then substitute x = 2.\nCalculation: f′(2) = ${n} × 2^${n - 1} = ${n} × ${power} = ${result}. We evaluate the derivative, not f(2).`,
            `Formule : si f(x) = x^n, la règle de dérivation des puissances donne f′(x) = n × x^(n−1). La dérivée décrit le taux de variation instantané de f.\nIci, n = ${n}. On multiplie par l’exposant et on diminue cet exposant de 1 : f′(x) = ${n} × x^${n - 1}. Ensuite seulement, on remplace x par 2.\nApplication : f′(2) = ${n} × 2^${n - 1} = ${n} × ${power} = ${result}. On calcule la dérivée en 2, et non f(2).`,
            `Formel: Für f(x) = x^n gilt die Potenzregel f′(x) = n × x^(n−1). Die Ableitung beschreibt die momentane Änderungsrate von f.\nHier ist n = ${n}. Man multipliziert mit dem Exponenten und verringert ihn um 1: f′(x) = ${n} × x^${n - 1}. Erst danach setzt man x = 2 ein.\nRechnung: f′(2) = ${n} × 2^${n - 1} = ${n} × ${power} = ${result}. Gesucht ist die Ableitung an der Stelle 2, nicht f(2).`,
        ];
    }

    if (key.startsWith('geometric-sum-')) {
        const a = value;
        const terms = [a,3*a,9*a,27*a].join(' + ');
        return [
            `Formula: S_n = a × (q^n − 1) / (q − 1), for q ≠ 1. Here a is the first term, q the multiplier between terms, and n the number of terms.\nWe have a = ${a}, q = 3 and n = 4. Multiplying each term by 3 gives the four terms ${terms}.\nCalculation: S_4 = ${a} × (3^4 − 1) / (3 − 1) = ${a} × 80/2 = ${40*a}. Check by addition: ${terms} = ${40*a}.`,
            `Formule : S_n = a × (q^n − 1) / (q − 1), pour q ≠ 1. a est le premier terme, q la raison multiplicative et n le nombre de termes.\nIci, a = ${a}, q = 3 et n = 4. On multiplie chaque terme par 3 pour obtenir le suivant : les quatre termes sont ${terms}.\nApplication : S_4 = ${a} × (3^4 − 1) / (3 − 1) = ${a} × 80/2 = ${40*a}. Vérification par addition : ${terms} = ${40*a}.`,
            `Formel: S_n = a × (q^n − 1) / (q − 1), für q ≠ 1. Dabei ist a das erste Glied, q der Faktor zwischen den Gliedern und n ihre Anzahl.\nHier sind a = ${a}, q = 3 und n = 4. Jedes Glied wird mit 3 multipliziert; die vier Glieder sind ${terms}.\nRechnung: S_4 = ${a} × (3^4 − 1) / (3 − 1) = ${a} × 80/2 = ${40*a}. Kontrolle durch Addition: ${terms} = ${40*a}.`,
        ];
    }

    if (key.startsWith('determinant-')) {
        const a=value, d=value+1, product=a*d, result=product-6;
        return [
            `Formula: for the matrix [[a,b],[c,d]], det = a × d − b × c. Multiply the main-diagonal entries, then subtract the product of the other diagonal.\nHere a = ${a}, b = 2, c = 3 and d = ${d}. The positions of these entries matter.\nCalculation: det = ${a} × ${d} − 2 × 3 = ${product} − 6 = ${result}.`,
            `Formule : pour la matrice [[a,b],[c,d]], det = a × d − b × c. On multiplie les éléments de la diagonale principale, puis on soustrait le produit de l’autre diagonale.\nIci, a = ${a}, b = 2, c = 3 et d = ${d}. La position des coefficients est importante.\nApplication : det = ${a} × ${d} − 2 × 3 = ${product} − 6 = ${result}.`,
            `Formel: Für die Matrix [[a,b],[c,d]] gilt det = a × d − b × c. Man multipliziert die Einträge der Hauptdiagonale und zieht das Produkt der anderen Diagonale ab.\nHier sind a = ${a}, b = 2, c = 3 und d = ${d}. Die Positionen der Einträge sind wichtig.\nRechnung: det = ${a} × ${d} − 2 × 3 = ${product} − 6 = ${result}.`,
        ];
    }

    if (key.startsWith('logarithm-')) {
        const n=2**value;
        return [
            `Rule: log_b(x) = y means b^y = x, with b > 0, b ≠ 1 and x > 0. A logarithm asks which exponent produces the given number.\nHere the base is b = 2 and x = ${n}, so we seek y such that 2^y = ${n}.\nCalculation: 2^${value} = ${n}; therefore log₂(${n}) = ${value}.`,
            `Règle : log_b(x) = y signifie b^y = x, avec b > 0, b ≠ 1 et x > 0. Un logarithme cherche l’exposant qui permet d’obtenir le nombre donné.\nIci, la base est b = 2 et x = ${n}. On cherche donc y tel que 2^y = ${n}.\nApplication : 2^${value} = ${n} ; donc log₂(${n}) = ${value}.`,
            `Regel: log_b(x) = y bedeutet b^y = x, mit b > 0, b ≠ 1 und x > 0. Der Logarithmus fragt nach dem Exponenten, der die angegebene Zahl ergibt.\nHier sind die Basis b = 2 und x = ${n}. Gesucht ist also y mit 2^y = ${n}.\nRechnung: 2^${value} = ${n}; daher ist log₂(${n}) = ${value}.`,
        ];
    }

    if (key.startsWith('without-replacement-')) {
        const red=value, total=2*value+1;
        const result=fraction(red*(red-1),total*(total-1));
        return [
            `Formula: P(R₁ and R₂) = P(R₁) × P(R₂ | R₁), where R₁ and R₂ mean “red on the first draw” and “red on the second draw”. The vertical bar means “given that”.\nWith r red balls among t balls, P = (r/t) × ((r−1)/(t−1)). Without replacement, after drawing a red ball there is one fewer red ball AND one fewer ball in total. The draws are not independent.\nCalculation: r = ${red}, t = ${total}, so P = (${red}/${total}) × (${red-1}/${total-1}) = ${red*(red-1)}/${total*(total-1)} = ${result}.`,
            `Formule : P(R₁ et R₂) = P(R₁) × P(R₂ | R₁), où R₁ et R₂ signifient « rouge au premier tirage » et « rouge au second tirage ». La barre verticale signifie « sachant que ».\nAvec r boules rouges parmi t boules, P = (r/t) × ((r−1)/(t−1)). Sans remise, après avoir tiré une rouge, il reste une boule rouge de moins ET une boule de moins au total. Les tirages ne sont pas indépendants.\nApplication : r = ${red}, t = ${total}, donc P = (${red}/${total}) × (${red-1}/${total-1}) = ${red*(red-1)}/${total*(total-1)} = ${result}.`,
            `Formel: P(R₁ und R₂) = P(R₁) × P(R₂ | R₁). R₁ und R₂ bedeuten „beim ersten Zug rot“ und „beim zweiten Zug rot“. Der senkrechte Strich bedeutet „unter der Bedingung“.\nBei r roten unter t Kugeln gilt P = (r/t) × ((r−1)/(t−1)). Ohne Zurücklegen sind nach einer roten Kugel sowohl eine rote Kugel als auch insgesamt eine Kugel weniger vorhanden. Die Ziehungen sind nicht unabhängig.\nRechnung: r = ${red}, t = ${total}, also P = (${red}/${total}) × (${red-1}/${total-1}) = ${red*(red-1)}/${total*(total-1)} = ${result}.`,
        ];
    }

    if (key.startsWith('hexadecimal-')) {
        const q=Math.floor(value/16), r=value%16;
        const digits=value.toString(16).toUpperCase();
        const qDigit=q.toString(16).toUpperCase(), rDigit=r.toString(16).toUpperCase();
        return [
            `Rule: in base 16, a two-digit number has value 16 × q + r, where q is the quotient of division by 16 and r the remainder (0 ≤ r < 16). Digits A, B, C, D, E, F represent 10, 11, 12, 13, 14, 15.\nCalculation: ${value} = ${q} × 16 + ${r}. The quotient ${q} is digit ${qDigit} and the remainder ${r} is digit ${rDigit}. Read them in that order: ${digits}.`,
            `Règle : en base 16, un nombre à deux chiffres vaut 16 × q + r, où q est le quotient de la division par 16 et r le reste (0 ≤ r < 16). Les chiffres A, B, C, D, E, F représentent 10, 11, 12, 13, 14, 15.\nApplication : ${value} = ${q} × 16 + ${r}. Le quotient ${q} s’écrit ${qDigit} et le reste ${r} s’écrit ${rDigit}. On les lit dans cet ordre : ${digits}.`,
            `Regel: Im Stellenwertsystem zur Basis 16 hat eine zweistellige Zahl den Wert 16 × q + r. Dabei ist q der Quotient bei Division durch 16 und r der Rest (0 ≤ r < 16). A, B, C, D, E, F stehen für 10, 11, 12, 13, 14, 15.\nRechnung: ${value} = ${q} × 16 + ${r}. Der Quotient ${q} entspricht der Ziffer ${qDigit}, der Rest ${r} der Ziffer ${rDigit}. In dieser Reihenfolge ergibt das ${digits}.`,
        ];
    }

    if (key.startsWith('modular-')) {
        const q=Math.floor(32/value), r=32%value;
        return [
            `Formula: N = q × m + r, with 0 ≤ r < m. Here N is the dividend, m the positive divisor, q the integer quotient and r the remainder. Equivalently, r = N − q × m, where q = floor(N/m).\nFirst compute the power: N = 2^5 = 32. Divide by m = ${value}; the integer quotient is q = ${q}.\nCalculation: 32 = ${q} × ${value} + ${r}, so r = 32 − ${q} × ${value} = ${r}. This remainder is between 0 and ${value-1}.`,
            `Formule : N = q × m + r, avec 0 ≤ r < m. N est le dividende, m le diviseur positif, q le quotient entier et r le reste. On a donc r = N − q × m, avec q égal à la partie entière de N/m.\nOn calcule d’abord la puissance : N = 2^5 = 32. On divise par m = ${value} ; le quotient entier est q = ${q}.\nApplication : 32 = ${q} × ${value} + ${r}, donc r = 32 − ${q} × ${value} = ${r}. Ce reste est bien compris entre 0 et ${value-1}.`,
            `Formel: N = q × m + r, mit 0 ≤ r < m. Dabei ist N der Dividend, m der positive Divisor, q der ganzzahlige Quotient und r der Rest. Somit gilt r = N − q × m, wobei q = floor(N/m) ist.\nZuerst berechnet man die Potenz: N = 2^5 = 32. Bei Division durch m = ${value} ist der ganzzahlige Quotient q = ${q}.\nRechnung: 32 = ${q} × ${value} + ${r}, also r = 32 − ${q} × ${value} = ${r}. Der Rest liegt zwischen 0 und ${value-1}.`,
        ];
    }

    if (key.startsWith('arithmetic-sum-')) {
        const a=value, d=value-1, last=a+19*d, result=10*(a+last);
        return [
            `Formulas: u_n = a + (n−1)d and S_n = n × (a + u_n)/2. Here a is the first term, d the constant difference and n the number of terms. The average of the first and last terms is (a + u_n)/2.\nWe have a = ${a}, d = ${d} and n = 20. There are 19 steps between the first and twentieth terms: u_20 = ${a} + 19 × ${d} = ${last}.\nCalculation: S_20 = 20 × (${a} + ${last})/2 = 10 × ${a+last} = ${result}. This is also S_n = n/2 × (2a + (n−1)d).`,
            `Formules : u_n = a + (n−1)d et S_n = n × (a + u_n)/2. a est le premier terme, d la raison additive et n le nombre de termes. La moyenne du premier et du dernier terme vaut (a + u_n)/2.\nIci, a = ${a}, d = ${d} et n = 20. Il y a 19 écarts entre le premier et le vingtième terme : u_20 = ${a} + 19 × ${d} = ${last}.\nApplication : S_20 = 20 × (${a} + ${last})/2 = 10 × ${a+last} = ${result}. On retrouve aussi S_n = n/2 × (2a + (n−1)d).`,
            `Formeln: u_n = a + (n−1)d und S_n = n × (a + u_n)/2. Dabei ist a das erste Glied, d die konstante Differenz und n die Anzahl der Glieder. Der Mittelwert aus erstem und letztem Glied beträgt (a + u_n)/2.\nHier sind a = ${a}, d = ${d} und n = 20. Zwischen dem ersten und zwanzigsten Glied liegen 19 Schritte: u_20 = ${a} + 19 × ${d} = ${last}.\nRechnung: S_20 = 20 × (${a} + ${last})/2 = 10 × ${a+last} = ${result}. Gleichwertig gilt S_n = n/2 × (2a + (n−1)d).`,
        ];
    }
    throw new Error(`Unknown math family: ${key}`);
}
