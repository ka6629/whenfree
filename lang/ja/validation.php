<?php

return [
    'required' => ':attributeを入力してください。',
    'string' => ':attributeは文字で入力してください。',
    'email' => ':attributeの形式が正しくありません。',
    'lowercase' => ':attributeは小文字で入力してください。',
    'unique' => 'その:attributeはすでに使われています。',
    'confirmed' => ':attributeが確認用と一致しません。',
    'current_password' => 'パスワードが違います。',
    'integer' => ':attributeは整数で入力してください。',
    'date' => ':attributeは正しい日付で入力してください。',
    'date_format' => ':attributeの形式が正しくありません。',
    'after' => ':attributeは:dateより後にしてください。',
    'after_or_equal' => ':attributeは:date以降にしてください。',
    'in' => ':attributeの値が正しくありません。',
    'max' => [
        'string' => ':attributeは:max文字以内で入力してください。',
        'numeric' => ':attributeは:max以下にしてください。',
    ],
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
        'numeric' => ':attributeは:min以上にしてください。',
    ],
    'between' => [
        'numeric' => ':attributeは:minから:maxの間にしてください。',
    ],
    'password' => [
        'letters' => ':attributeには英字を1文字以上含めてください。',
        'mixed' => ':attributeには大文字と小文字を両方含めてください。',
        'numbers' => ':attributeには数字を1文字以上含めてください。',
        'symbols' => ':attributeには記号を1文字以上含めてください。',
    ],

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'current_password' => '現在のパスワード',
        'title' => 'タイトル',
        'date' => '日付',
        'day_of_week' => '曜日',
        'start_time' => '開始の時刻',
        'end_time' => '終了の時刻',
        'invite_code' => '招待コード',
        'needed_count' => '必要な人数',
    ],
];