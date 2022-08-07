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

    'accepted' => 'This field must be accepted.',
    'accepted_if' => 'This field must be accepted when :other is :value.',
    'active_url' => 'This is not a valid URL.',
    'after' => 'This must be a date after :date.',
    'after_or_equal' => 'This must be a date after or equal to :date.',
    'alpha' => 'This field must only contain letters.',
    'alpha_dash' => 'This field must only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'This field must only contain letters and numbers.',
    'array' => 'This field must be an array.',
    'before' => 'This must be a date before :date.',
    'before_or_equal' => 'This must be a date before or equal to :date.',
    'between' => [
        'array' => 'This content must have between :min and :max items.',
        'file' => 'This file must be between :min and :max kilobytes.',
        'numeric' => 'This value must be between :min and :max.',
        'string' => 'This string must be between :min and :max characters.',
    ],
    'boolean' => 'This field must be true or false.',
    'confirmed' => 'The confirmation does not match.',
    'current_password' => 'The password is incorrect.',
    'date' => 'This is not a valid date.',
    'date_equals' => 'This must be a date equal to :date.',
    'date_format' => 'This does not match the format :format.',
    'declined' => 'This value must be declined.',
    'declined_if' => 'This value must be declined when :other is :value.',
    'different' => 'This value must be different from :other.',
    'digits' => 'This must be :digits digits.',
    'digits_between' => 'This must be between :min and :max digits.',
    'dimensions' => 'This image has invalid dimensions.',
    'distinct' => 'This field has a duplicate value.',
    'email' => 'This must be a valid email address.',
    'ends_with' => 'This must end with one of the following: :values.',
    'enum' => 'The selected value is invalid.',
    'exists' => 'The selected value is invalid.',
    'file' => 'The content must be a file.',
    'filled' => 'This field must have a value.',
    'gt' => [
        'array' => 'The content must have more than :value items.',
        'file' => 'The file size must be greater than :value kilobytes.',
        'numeric' => 'The value must be greater than :value.',
        'string' => 'The string must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The content must have :value items or more.',
        'file' => 'The file size must be greater than or equal to :value kilobytes.',
        'numeric' => 'The value must be greater than or equal to :value.',
        'string' => 'The string must be greater than or equal to :value characters.',
    ],
    'image' => 'This must be an image.',
    'in' => 'The selected value is invalid.',
    'in_array' => 'This value does not exist in :other.',
    'integer' => 'This must be an integer.',
    'ip' => 'This must be a valid IP address.',
    'ipv4' => 'This must be a valid IPv4 address.',
    'ipv6' => 'This must be a valid IPv6 address.',
    'json' => 'This must be a valid JSON string.',
    'lt' => [
        'array' => 'The content must have less than :value items.',
        'file' => 'The file size must be less than :value kilobytes.',
        'numeric' => 'The value must be less than :value.',
        'string' => 'The string must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The content must not have more than :value items.',
        'file' => 'The file size must be less than or equal to :value kilobytes.',
        'numeric' => 'The value must be less than or equal to :value.',
        'string' => 'The string must be less than or equal to :value characters.',
    ],
    'mac_address' => 'The value must be a valid MAC address.',
    'max' => [
        'array' => 'The content must not have more than :max items.',
        'file' => 'The file size must not be greater than :max kilobytes.',
        'numeric' => 'The value must not be greater than :max.',
        'string' => 'The string must not be greater than :max characters.',
    ],
    'mimes' => 'This must be a file of type: :values.',
    'mimetypes' => 'This must be a file of type: :values.',
    'min' => [
        'array' => 'The value must have at least :min items.',
        'file' => 'The file size must be at least :min kilobytes.',
        'numeric' => 'The value must be at least :min.',
        'string' => 'The string must be at least :min characters.',
    ],
    'multiple_of' => 'The value must be a multiple of :value.',
    'not_in' => 'The selected value is invalid.',
    'not_regex' => 'This format is invalid.',
    'numeric' => 'This must be a number.',
    'password' => 'The password is incorrect.',
    'present' => 'This field must be present.',
    'regex' => 'This format is invalid.',
    'required' => 'This field is required.',
    'required_array_keys' => 'This field must contain entries for: :values.',
    'required_if' => 'This field is required when :other is :value.',
    'required_unless' => 'This field is required unless :other is in :values.',
    'required_with' => 'This field is required when :values is present.',
    'required_with_all' => 'This field is required when :values are present.',
    'required_without' => 'This field is required when :values is not present.',
    'required_without_all' => 'This field is required when none of :values are present.',
    'prohibited' => 'This field is prohibited.',
    'prohibited_if' => 'This field is prohibited when :other is :value.',
    'prohibited_unless' => 'This field is prohibited unless :other is in :values.',
    'prohibits' => 'This field prohibits :other from being present.',
    'same' => 'The value of this field must match the one from :other.',
    'size' => [
        'array' => 'The content must contain :size items.',
        'file' => 'The file size must be :size kilobytes.',
        'numeric' => 'The value must be :size.',
        'string' => 'The string must be :size characters.',
    ],
    'starts_with' => 'This must start with one of the following: :values.',
    'string' => 'This must be a string.',
    'timezone' => 'This must be a valid timezone.',
    'unique' => 'This has already been taken.',
    'uploaded' => 'This failed to upload.',
    'url' => 'This must be a valid URL.',
    'uuid' => 'This must be a valid UUID.',

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
        'agent-number-exceeded' => 'The number of Agents cannot be reduced as the current number of players with the Agent role will be greater than planned.',
        'avatar-busy' => 'Avatar is busy.',
        'black-ticket-active' => 'Black Ticket is still active.',
        'boundary-not-closed' => 'The boundary has not been closed.',
        'catchers-number-exceeded' => 'The required number of players when catching must not exceed the total number of Policemen.',
        'complete-boundary' => 'Complete the boundary.',
        'eagle-number-exceeded' => 'The number of Eagles cannot be reduced as the current number of players with the Eagles role will be greater than planned.',
        'endpoint-name-not-found' => 'Endpoint name not found.',
        'external-api-error' => 'External api error (:api).',
        'fake-position-active' => 'Fake Position is still active.',
        'fatty-man-number-exceeded' => 'It is not possible to reduce the number of Fat Man, as the current number of players with the Fat Man role will be greater than planned.',
        'game-already-started' => 'The game has already started.',
        'game-being-prepared' => 'The game is being prepared.',
        'game-is-over' => 'Game is over.',
        'incorrect-boundary-vertices-number' => 'An incorrect number of boundary vertices given.',
        'incorrect-code' => 'Incorrect code.',
        'incorrect-database-search' => 'You can only use perfect matches (=) or similar matches (LIKE).',
        'invalid-boundary-shape' => 'Invalid boundary shape. It must be a convex figure.',
        'invalid-coordinate-format' => 'Invalid geographic coordinate format.',
        'invalid-log-type' => 'You can only use the (mail) or (log) log type.',
        'limit-exceeded' => 'The action limit has been exceeded. You can try again in :seconds seconds.',
        'location-beyond-boundary' => 'The location is beyond the boundary.',
        'malicious-request' => 'Sending requests by non-cooperators is strictly prohibited. All connection attempts are monitored and can have consequences!',
        'max-player-number-reached' => 'The maximum player number has been reached.',
        'no-black-ticket-available' => 'You no longer have Black Tickets.',
        'no-fake-position-available' => 'You no longer have Fake Positions.',
        'no-white-ticket-available' => 'You no longer have White Tickets.',
        'no-permission' => 'You cannot perform the action given.',
        'not-enough-players' => 'Too few players in the room. Do you want to supplement them with bots?',
        'pegasus-number-exceeded' => 'The number of Pegasus cannot be reduced as the current number of players with the Pegasus role will be greater than planned.',
        'players-number-exceeded' => 'The game cannot start because the number of players has been exceeded.',
        'policemen-number-exceeded' => 'The number of players on the side of catching and having special skills must not exceed the total number of Policemen.',
        'policeman-visibility-radius' => 'The radius of detection of Thieves by Police officers must not be smaller than the radius of catching.',
        'thief-number-exceeded' => 'The number of Thieves cannot be reduced as the current number of players with the Thief role will be greater than planned.',
        'undefined-request-fields-detected' => 'Undefined request fields detected: fields.',
        'user-is-not-in-room' => 'The specified user is not in the room.',
        'voting-already-started' => 'Voting has already started.',
        'voting-limit' => 'You can start voting in :seconds seconds.',
        'warnings-number-exceeded' => 'Number of warnings exceeded.',
        'you-are-already-in-another-room' => "You're still in another room. You have to leave it first.",
        'you-left-the-room' => "You left the room.",
        'you-must-set-player-name' => 'You must first set a player name.',
        'you-have-been-banned' => 'You have been banned from this room.',
    ],
];
