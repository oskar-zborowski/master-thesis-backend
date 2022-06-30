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

    'accepted' => 'The :attribute must be accepted.',
    'accepted_if' => 'The :attribute must be accepted when :other is :value.',
    'active_url' => 'The :attribute is not a valid URL.',
    'after' => 'The :attribute must be a date after :date.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'alpha' => 'The :attribute must only contain letters.',
    'alpha_dash' => 'The :attribute must only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'The :attribute must only contain letters and numbers.',
    'array' => 'The :attribute must be an array.',
    'before' => 'The :attribute must be a date before :date.',
    'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
    'between' => [
        'array' => 'The :attribute must have between :min and :max items.',
        'file' => 'The :attribute must be between :min and :max kilobytes.',
        'numeric' => 'The :attribute must be between :min and :max.',
        'string' => 'The :attribute must be between :min and :max characters.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'current_password' => 'The password is incorrect.',
    'date' => 'The :attribute is not a valid date.',
    'date_equals' => 'The :attribute must be a date equal to :date.',
    'date_format' => 'The :attribute does not match the format :format.',
    'declined' => 'The :attribute must be declined.',
    'declined_if' => 'The :attribute must be declined when :other is :value.',
    'different' => 'The :attribute and :other must be different.',
    'digits' => 'The :attribute must be :digits digits.',
    'digits_between' => 'The :attribute must be between :min and :max digits.',
    'dimensions' => 'The :attribute has invalid image dimensions.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'email' => 'The :attribute must be a valid email address.',
    'ends_with' => 'The :attribute must end with one of the following: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'file' => 'The :attribute must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'array' => 'The :attribute must have more than :value items.',
        'file' => 'The :attribute must be greater than :value kilobytes.',
        'numeric' => 'The :attribute must be greater than :value.',
        'string' => 'The :attribute must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The :attribute must have :value items or more.',
        'file' => 'The :attribute must be greater than or equal to :value kilobytes.',
        'numeric' => 'The :attribute must be greater than or equal to :value.',
        'string' => 'The :attribute must be greater than or equal to :value characters.',
    ],
    'image' => 'The :attribute must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field does not exist in :other.',
    'integer' => 'The :attribute must be an integer.',
    'ip' => 'The :attribute must be a valid IP address.',
    'ipv4' => 'The :attribute must be a valid IPv4 address.',
    'ipv6' => 'The :attribute must be a valid IPv6 address.',
    'json' => 'The :attribute must be a valid JSON string.',
    'lt' => [
        'array' => 'The :attribute must have less than :value items.',
        'file' => 'The :attribute must be less than :value kilobytes.',
        'numeric' => 'The :attribute must be less than :value.',
        'string' => 'The :attribute must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The :attribute must not have more than :value items.',
        'file' => 'The :attribute must be less than or equal to :value kilobytes.',
        'numeric' => 'The :attribute must be less than or equal to :value.',
        'string' => 'The :attribute must be less than or equal to :value characters.',
    ],
    'mac_address' => 'The :attribute must be a valid MAC address.',
    'max' => [
        'array' => 'The :attribute must not have more than :max items.',
        'file' => 'The :attribute must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute must not be greater than :max.',
        'string' => 'The :attribute must not be greater than :max characters.',
    ],
    'mimes' => 'The :attribute must be a file of type: :values.',
    'mimetypes' => 'The :attribute must be a file of type: :values.',
    'min' => [
        'array' => 'The :attribute must have at least :min items.',
        'file' => 'The :attribute must be at least :min kilobytes.',
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'multiple_of' => 'The :attribute must be a multiple of :value.',
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute format is invalid.',
    'numeric' => 'The :attribute must be a number.',
    'password' => 'The password is incorrect.',
    'present' => 'The :attribute field must be present.',
    'prohibited' => 'The :attribute field is prohibited.',
    'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    'prohibits' => 'The :attribute field prohibits :other from being present.',
    'regex' => 'The :attribute format is invalid.',
    'required' => 'The :attribute field is required.',
    'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute and :other must match.',
    'size' => [
        'array' => 'The :attribute must contain :size items.',
        'file' => 'The :attribute must be :size kilobytes.',
        'numeric' => 'The :attribute must be :size.',
        'string' => 'The :attribute must be :size characters.',
    ],
    'starts_with' => 'The :attribute must start with one of the following: :values.',
    'string' => 'The :attribute must be a string.',
    'timezone' => 'The :attribute must be a valid timezone.',
    'unique' => 'The :attribute has already been taken.',
    'uploaded' => 'The :attribute failed to upload.',
    'url' => 'The :attribute must be a valid URL.',
    'uuid' => 'The :attribute must be a valid UUID.',

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
        'avatar-busy' => 'Avatar is busy.',
        'boundary-not-closed' => 'The boundary has not been closed.',
        'catchers-number-exceeded' => 'The required number of players when catching must not exceed the total number of policemen.',
        'endpoint-name-not-found' => 'Endpoint name not found.',
        'game-already-started' => 'The game has already started.',
        'game-is-over' => 'Game is over.',
        'incorrect-code' => 'Incorrect code.',
        'incorrect-database-search' => 'You can only use perfect matches (=) or similar matches (LIKE).',
        'incorrect-boundary-vertices-number' => 'An incorrect number of boundary vertices given.',
        'invalid-boundary-shape' => 'Invalid boundary shape. It must be a convex figure.',
        'invalid-coordinate-format' => 'Invalid geographic coordinate format.',
        'invalid-log-type' => 'You can only use the (mail) or (log) log type.',
        'limit-exceeded' => 'The action limit has been exceeded. You can try again in :seconds seconds.',
        'malicious-request' => 'Sending requests by non-cooperators is strictly prohibited. All connection attempts are monitored and can have consequences!',
        'max-player-number-reached' => 'The maximum player number has been reached.',
        'no-permission' => 'You cannot perform the action given.',
        'undefined-request-fields-detected' => 'Undefined request fields detected: fields.',
        'user-is-not-in-room' => 'The specified user is not in the room.',
        'policemen-number-exceeded' => 'The number of players on the side of catching and having special skills must not exceed the total number of policemen.',
        'you-are-already-in-another-room' => "You're still in another room. You have to leave it first.",
        'you-must-set-player-name' => 'You must first set a player name.',
        'you-have-been-banned' => 'You have been banned from this room.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'address' => 'Address',
        'age' => 'Age',
        'amount' => 'Amount',
        'area' => 'Area',
        'available' => 'Available',
        'birthday' => 'Birthday',
        'body' => 'Body',
        'city' => 'City',
        'content' => 'Content',
        'country' => 'Country',
        'created_at' => 'Creation date',
        'creator' => 'Creator',
        'current_password' => 'Current password',
        'date' => 'Date',
        'date_of_birth' => 'Date of birth',
        'day' => 'days',
        'deleted_at' => 'Date of removal',
        'description' => 'Description',
        'district' => 'District',
        'duration' => 'Duration',
        'email' => 'Email',
        'excerpt' => 'Excerpt',
        'filter' => 'Filter',
        'first_name' => 'First name',
        'gender' => 'Gender',
        'group' => 'Group',
        'hour' => 'hours',
        'image' => 'Image',
        'last_name' => 'Last name',
        'lesson' => 'Lesson',
        'line_address_1' => 'Line Address 1',
        'line_address_2' => 'Line Address 2',
        'message' => 'Message',
        'middle_name' => 'Middle name',
        'minute' => 'minutes',
        'mobile' => 'Mobile',
        'month' => 'months',
        'name' => 'Name',
        'national_code' => 'National code',
        'number' => 'Number',
        'password' => 'Password',
        'password_confirmation' => 'Password confirmation',
        'phone' => 'Phone',
        'photo' => 'Photo',
        'postal_code' => 'Postal code',
        'price' => 'Price',
        'province' => 'Province',
        'recaptcha_response_field' => 'Recaptcha response field',
        'restored_at' => 'Date of restoration',
        'remember' => 'Remember',
        'result_text_under_image' => 'Result text under image',
        'role' => 'Role',
        'second' => 'seconds',
        'sex' => 'Sex',
        'short_text' => 'Short text',
        'size' => 'Size',
        'state' => 'State',
        'street' => 'Street',
        'student' => 'Student',
        'subject' => 'Subject',
        'teacher' => 'Teacher',
        'terms' => 'Terms',
        'test_description' => 'Test description',
        'test_locale' => 'Test locale',
        'test_name' => 'Test name',
        'text' => 'Text',
        'time' => 'Time',
        'title' => 'Title',
        'updated_at' => 'Update date',
        'username' => 'Username',
        'year' => 'years',
    ],
];
