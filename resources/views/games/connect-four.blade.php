<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Connect Four') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f8; color: #202039; font-family: {{ auth()->user()->interfaceFont() }}; font-size: {{ auth()->user()->interfaceTextSize() }}; }
        main { max-width: 740px; margin: auto; padding: 24px 16px 48px; }
        a { color: #4f46e5; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        h1 { margin-bottom: 8px; }
        .subtitle, .rules { color: #62627a; line-height: 1.6; }
        .card { background: white; padding: clamp(12px, 3vw, 28px); border: 1px solid #e0e0e5; border-radius: 20px; margin-top: 24px; box-shadow: 0 4px 16px #20203908; }
        .status { font-size: 1.15em; font-weight: 700; padding: 14px; background: #eef0ff; border-radius: 10px; display: flex; align-items: center; gap: 10px; }
        .marker { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #202039; flex-shrink: 0; }
        .red { background: #dc2626; color: white; }
        .yellow { background: #facc15; color: #202039; }
        .columns, .board { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: clamp(4px, 1.4vw, 10px); padding: 12px; }
        .columns { margin-top: 8px; }
        button { font: inherit; cursor: pointer; border: 0; border-radius: 8px; }
        .columns button { width: 100%; padding: 12px 0; background: #eef0ff; color: #3730a3; font-weight: 700; }
        .columns button:hover:enabled { background: #c7d2fe; }
        .columns button:disabled { opacity: .35; cursor: not-allowed; }
        .board { background: #3730a3; border-radius: 14px; }
        .cell { aspect-ratio: 1; border-radius: 50%; background: #f4f4f8; display: flex; align-items: center; justify-content: center; font-weight: 800; box-shadow: inset 0 3px 5px #0003; }
        .cell.red { background: #dc2626; }
        .cell.yellow { background: #facc15; }
        .winning { outline: 3px solid white; outline-offset: -5px; }
        .actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
        .restart { padding: 12px 18px; background: #4f46e5; color: white; font-weight: 600; }
        .error { padding: 12px; background: #fef2f2; color: #991b1b; border-radius: 8px; }
        :focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
    </style>
</head>
<body>
<main>
    <header>
        <a href="{{ route('accueil') }}">&larr; {{ __('Back to home') }}</a>
        @include('partials.user-menu')
    </header>
    <h1>{{ __('Connect Four') }}</h1>
    <p class="subtitle">{{ __('Two players, one screen. Align four discs to win.') }}</p>
    <section class="card" aria-label="{{ __('Connect Four') }}">
        <div class="status" role="status" aria-live="polite">
            @if($game['status'] === 'draw')
                {{ __('Draw! The board is full.') }}
            @else
                <span class="marker {{ $game['winner'] ?? $game['current_player'] }}" aria-hidden="true"></span>
                @if($game['status'] === 'won')
                    {{ __(':name wins!', ['name' => $players[$game['winner'] === 'red' ? 0 : 1]]) }}
                @else
                    {{ __(':name to play', ['name' => $players[$game['current_player'] === 'red' ? 0 : 1]]) }}
                @endif
            @endif
        </div>
        <p>{{ $players[0] }} ({{ __('Red') }}, 1) · {{ $players[1] }} ({{ __('Yellow') }}, 2)</p>
        @error('column')
            <p class="error" role="alert">{{ $message }}</p>
        @enderror
        @error('move')
            <p class="error" role="alert">{{ $message }}</p>
        @enderror
        <form method="POST" action="{{ route('connect-four.play') }}">
            @csrf
            <input type="hidden" name="move" value="{{ $game['moves'] }}">
            <div class="columns">
                @for($column = 0; $column < 7; $column++)
                    <button type="submit" name="column" value="{{ $column }}"
                        aria-label="{{ __('Drop a disc in column :number', ['number' => $column + 1]) }}"
                        @disabled($game['status'] !== 'playing' || $game['board'][0][$column] !== null)>
                        <span aria-hidden="true">&darr; {{ $column + 1 }}</span>
                    </button>
                @endfor
            </div>
        </form>
        <div class="board" role="table" aria-label="{{ __('Game board, 6 rows and 7 columns') }}">
            @foreach($game['board'] as $row => $cells)
                <div role="row" style="display: contents">
                    @foreach($cells as $column => $cell)
                        <div role="cell" class="cell {{ $cell }} {{ in_array([$row, $column], $game['winning_cells'], true) ? 'winning' : '' }}"
                            aria-label="{{ __('Row :row, column :column: :value', ['row' => $row + 1, 'column' => $column + 1, 'value' => $cell === null ? __('Empty') : ($cell === 'red' ? __('Red') : __('Yellow'))]) }}">
                            <span aria-hidden="true">{{ $cell === 'red' ? '1' : ($cell === 'yellow' ? '2' : '') }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="actions">
            <span>{{ __('Moves played: :count', ['count' => $game['moves']]) }}</span>
            <a href="{{ route('games.players', ['game' => 'connect-four']) }}">{{ __('Change players') }}</a>
            <form method="POST" action="{{ route('connect-four.restart') }}">
                @csrf
                <button class="restart" type="submit">{{ __('New game') }}</button>
            </form>
        </div>
    </section>
    <p class="rules">{{ __('Player 1 is red; player 2 is yellow. Take turns choosing a column. Discs fall to the bottom. Align four horizontally, vertically or diagonally. A full board without a winner is a draw.') }}</p>
</main>
</body>
</html>
