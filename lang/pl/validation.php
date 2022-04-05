<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Pole musi zostać zaakceptowane.',
    'accepted_if' => 'Pole musi zostać zaakceptowane gdy :other ma wartość :value.',
    'active_url' => 'Pole jest nieprawidłowym adresem URL.',
    'after' => 'Pole musi być datą późniejszą od :date.',
    'after_or_equal' => 'Pole musi być datą nie wcześniejszą niż :date.',
    'alpha' => 'Pole może zawierać jedynie litery.',
    'alpha_dash' => 'Pole może zawierać jedynie litery, cyfry i myślniki.',
    'alpha_num' => 'Pole może zawierać jedynie litery i cyfry.',
    'array' => 'Pole musi być tablicą.',
    'before' => 'Pole musi być datą wcześniejszą od :date.',
    'before_or_equal' => 'Pole musi być datą nie późniejszą niż :date.',
    'between' => [
        'array' => 'Pole musi składać się z :min - :max elementów.',
        'file' => 'Pole musi zawierać się w granicach :min - :max kilobajtów.',
        'numeric' => 'Pole musi zawierać się w granicach :min - :max.',
        'string' => 'Pole musi zawierać się w granicach :min - :max znaków.',
    ],
    'boolean' => 'Pole musi mieć wartość logiczną prawda albo fałsz.',
    'confirmed' => 'Potwierdzenie pola nie zgadza się.',
    'current_password' => 'Hasło jest nieprawidłowe.',
    'date' => 'Pole nie jest prawidłową datą.',
    'date_equals' => 'Pole musi być datą równą :date.',
    'date_format' => 'Pole nie jest w formacie :format.',
    'declined' => 'Wartość musi zostać odrzucona.',
    'declined_if' => 'Wartość musi zostać odrzucona, gdy :other ma wartość :value.',
    'different' => 'Pole oraz :other muszą się różnić.',
    'digits' => 'Pole musi składać się z :digits cyfr.',
    'digits_between' => 'Pole musi mieć od :min do :max cyfr.',
    'dimensions' => 'Pole ma niepoprawne wymiary.',
    'distinct' => 'Pole ma zduplikowane wartości.',
    'email' => 'Pole nie jest poprawnym adresem e-mail.',
    'ends_with' => 'Pole musi kończyć się jedną z następujących wartości: :values.',
    'enum' => 'Wybrana wartość jest nieprawidłowa.',
    'exists' => 'Zaznaczona wartość jest nieprawidłowa.',
    'file' => 'Pole musi być plikiem.',
    'filled' => 'Pole nie może być puste.',
    'gt' => [
        'array' => 'Pole musi mieć więcej niż :value elementów.',
        'file' => 'Pole musi być większe niż :value kilobajtów.',
        'numeric' => 'Pole musi być większe niż :value.',
        'string' => 'Pole musi być dłuższe niż :value znaków.',
    ],
    'gte' => [
        'array' => 'Pole musi mieć :value lub więcej elementów.',
        'file' => 'Pole musi być większe lub równe :value kilobajtów.',
        'numeric' => 'Pole musi być większe lub równe :value.',
        'string' => 'Pole musi być dłuższe lub równe :value znaków.',
    ],
    'image' => 'Pole musi być obrazkiem.',
    'in' => 'Zaznaczony element jest nieprawidłowy.',
    'in_array' => 'Pole nie znajduje się w :other.',
    'integer' => 'Pole musi być liczbą całkowitą.',
    'ip' => 'Pole musi być prawidłowym adresem IP.',
    'ipv4' => 'Pole musi być prawidłowym adresem IPv4.',
    'ipv6' => 'Pole musi być prawidłowym adresem IPv6.',
    'json' => 'Pole musi być poprawnym ciągiem znaków JSON.',
    'lt' => [
        'array' => 'Pole musi mieć mniej niż :value elementów.',
        'file' => 'Pole musi być mniejsze niż :value kilobajtów.',
        'numeric' => 'Pole musi być mniejsze niż :value.',
        'string' => 'Pole musi być krótsze niż :value znaków.',
    ],
    'lte' => [
        'array' => 'Pole musi mieć :value lub mniej elementów.',
        'file' => 'Pole musi być mniejsze lub równe :value kilobajtów.',
        'numeric' => 'Pole musi być mniejsze lub równe :value.',
        'string' => 'Pole musi być krótsze lub równe :value znaków.',
    ],
    'mac_address' => 'Pole musi być prawidłowym adresem MAC.',
    'max' => [
        'array' => 'Pole nie może mieć więcej niż :max elementów.',
        'file' => 'Pole nie może być większe niż :max kilobajtów.',
        'numeric' => 'Pole nie może być większe niż :max.',
        'string' => 'Pole nie może być dłuższe niż :max znaków.',
    ],
    'mimes' => 'Pole musi być plikiem typu :values.',
    'mimetypes' => 'Pole musi być plikiem typu :values.',
    'min' => [
        'array' => 'Pole musi mieć przynajmniej :min elementów.',
        'file' => 'Pole musi mieć przynajmniej :min kilobajtów.',
        'numeric' => 'Pole musi być nie mniejsze od :min.',
        'string' => 'Pole musi mieć przynajmniej :min znaków.',
    ],
    'multiple_of' => 'Pole musi być wielokrotnością wartości :value',
    'not_in' => 'Zaznaczona wartość jest nieprawidłowa.',
    'not_regex' => 'Format pola jest nieprawidłowy.',
    'numeric' => 'Pole musi być liczbą.',
    'password' => 'Hasło jest nieprawidłowe.',
    'present' => 'Pole musi być obecne.',
    'prohibited' => 'To pole jest zabronione.',
    'prohibited_if' => 'To pole jest zabronione, gdy :other to :value.',
    'prohibited_unless' => 'To pole jest zabronione, chyba że :other jest w :values.',
    'prohibits' => 'Pole zabrania obecności :other.',
    'regex' => 'Format pola jest nieprawidłowy.',
    'required' => 'Pole jest wymagane.',
    'required_array_keys' => 'Pole musi zawierać wartości: :values.',
    'required_if' => 'Pole jest wymagane gdy :other ma wartość :value.',
    'required_unless' => 'Pole jest wymagane jeżeli :other nie znajduje się w :values.',
    'required_with' => 'Pole jest wymagane gdy :values jest obecny.',
    'required_with_all' => 'Pole jest wymagane gdy wszystkie :values są obecne.',
    'required_without' => 'Pole jest wymagane gdy :values nie jest obecny.',
    'required_without_all' => 'Pole jest wymagane gdy żadne z :values nie są obecne.',
    'same' => 'Pole i :other muszą być takie same.',
    'size' => [
        'array' => 'Pole musi zawierać :size elementów.',
        'file' => 'Pole musi mieć :size kilobajtów.',
        'numeric' => 'Pole musi mieć :size.',
        'string' => 'Pole musi mieć :size znaków.',
    ],
    'starts_with' => 'Pole musi zaczynać się jedną z następujących wartości: :values.',
    'string' => 'Pole musi być ciągiem znaków.',
    'timezone' => 'Pole musi być prawidłową strefą czasową.',
    'unique' => 'Taka wartość już występuje.',
    'uploaded' => 'Nie udało się wgrać pliku.',
    'url' => 'Format pola jest nieprawidłowy.',
    'uuid' => 'Pole musi być poprawnym identyfikatorem UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
        'boundary-not-closed' => 'Granica nie została zamknięta.',
        'game-already-started' => 'Gra już się rozpoczęła.',
        'incorrect-code' => 'Nieprawidłowy kod.',
        'incorrect-database-search' => 'Możesz użyć jedynie dopasowań idealnych (=), albo dopasowań podobnych (LIKE).',
        'invalid-boundary-shape' => 'Nieprawidłowy kształt granicy.',
        'invalid-coordinate-format' => 'Nieprawidłowy format współrzędnych geograficznych.',
        'limit-exceeded' => 'Przekroczono limit żądań. Możesz spróbować ponownie za :seconds sekund.',
        'malicious-request' => 'Wysyłanie żądań przez podmioty niewspółpracujące jest surowo zabronione. Wszystkie próby nawiązywania połączenia są monitorowane i mogą wiązać się z konsekwencjami!',
        'monitoring-central-outside-boundary' => 'Centrala monitoringu musi się całkowicie mieścić w obszarze gry.',
        'monitoring-outside-boundary' => 'Kamery monitoringu muszą się całkowicie mieścić w obszarze gry.',
        'no-permission' => 'Nie masz uprawnień do danego zasobu.',
        'number-of-external-vertices-exceeded' => 'Przekroczono maksymalną liczbę wierzchołków zewnętrznej granicy.',
        'number-of-internal-boundaries-exceeded' => 'Przekroczono maksymalną liczbę wewnętrznych granic.',
        'number-of-internal-vertices-exceeded' => 'Przekroczono maksymalną liczbę wierzchołków wewnętrznej granicy.',
        'overlapping-monitoring-centrals' => 'Centrale monitoringu nie mogą się idealnie pokrywać.',
        'overlapping-monitorings' => 'Kamery monitoringu nie mogą się idealnie pokrywać.',
        'user-is-not-in-room' => 'Podany użytkownik nie znajduje się w pokoju.',
        'you-are-already-in-room' => 'Już jesteś w pokoju.',
        'you-have-been-banned' => 'Zostałeś/aś zbanowany/a w tym pokoju.',
    ],
];
