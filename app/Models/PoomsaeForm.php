<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name'])]
class PoomsaeForm extends Model
{
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_poomsae_form');
    }

    public static function numberFromName(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/u', ' ', $name));
        $lower = mb_strtolower($cleaned);

        $map = [
            'taegeuk 1' => 1, 'taeguk 1' => 1, 'taegeuk 1 jang' => 1, 'taeguk 1 jang' => 1,
            'taegeuk 2' => 2, 'taeguk 2' => 2, 'taegeuk 2 jang' => 2, 'taeguk 2 jang' => 2,
            'taegeuk 3' => 3, 'taeguk 3' => 3, 'taegeuk 3 jang' => 3, 'taeguk 3 jang' => 3,
            'taegeuk 4' => 4, 'taeguk 4' => 4, 'taegeuk 4 jang' => 4, 'taeguk 4 jang' => 4,
            'taegeuk 5' => 5, 'taeguk 5' => 5, 'taegeuk 5 jang' => 5, 'taeguk 5 jang' => 5,
            'taegeuk 6' => 6, 'taeguk 6' => 6, 'taegeuk 6 jang' => 6, 'taeguk 6 jang' => 6,
            'taegeuk 7' => 7, 'taeguk 7' => 7, 'taegeuk 7 jang' => 7, 'taeguk 7 jang' => 7,
            'taegeuk 8' => 8, 'taeguk 8' => 8, 'taegeuk 8 jang' => 8, 'taeguk 8 jang' => 8,
            'koryo' => 9, 'keumgang' => 10, 'taebaek' => 11, 'pyongwon' => 12,
            'shipjin' => 13, 'sipjin' => 13, 'jitae' => 14, 'chonkwon' => 15,
            'hansu' => 16, 'ilyo' => 17,
            'فرم ۱' => 1, 'فرم 1' => 1, '1' => 1, '۱' => 1, 'تایگوک ۱' => 1, 'تایگوک 1' => 1,
            'فرم ۲' => 2, 'فرم 2' => 2, '2' => 2, '۲' => 2, 'تایگوک ۲' => 2, 'تایگوک 2' => 2,
            'فرم ۳' => 3, 'فرم 3' => 3, '3' => 3, '۳' => 3, 'تایگوک ۳' => 3, 'تایگوک 3' => 3,
            'فرم ۴' => 4, 'فرم 4' => 4, '4' => 4, '۴' => 4, 'تایگوک ۴' => 4, 'تایگوک 4' => 4,
            'فرم ۵' => 5, 'فرم 5' => 5, '5' => 5, '۵' => 5, 'تایگوک ۵' => 5, 'تایگوک 5' => 5,
            'فرم ۶' => 6, 'فرم 6' => 6, '6' => 6, '۶' => 6, 'تایگوک ۶' => 6, 'تایگوک 6' => 6,
            'فرم ۷' => 7, 'فرم 7' => 7, '7' => 7, '۷' => 7, 'تایگوک ۷' => 7, 'تایگوک 7' => 7,
            'فرم ۸' => 8, 'فرم 8' => 8, '8' => 8, '۸' => 8, 'تایگوک ۸' => 8, 'تایگوک 8' => 8,
            'کوریو' => 9, 'فرم ۹' => 9, 'فرم 9' => 9, '9' => 9, '۹' => 9,
            'کومگانگ' => 10, 'فرم ۱۰' => 10, 'فرم 10' => 10, '10' => 10, '۱۰' => 10,
            'ته بک' => 11, 'ته‌بک' => 11, 'فرم ۱۱' => 11, 'فرم 11' => 11, '11' => 11, '۱۱' => 11,
            'پیونگ وان' => 12, 'پیونگ‌وان' => 12, 'فرم ۱۲' => 12, 'فرم 12' => 12, '12' => 12, '۱۲' => 12,
            'شیپ جین' => 13, 'شیپ‌جین' => 13, 'فرم ۱۳' => 13, 'فرم 13' => 13, '13' => 13, '۱۳' => 13,
            'جی ته' => 14, 'جی‌ته' => 14, 'فرم ۱۴' => 14, 'فرم 14' => 14, '14' => 14, '۱۴' => 14,
            'چون کوان' => 15, 'چون‌کوان' => 15, 'فرم ۱۵' => 15, 'فرم 15' => 15, '15' => 15, '۱۵' => 15,
            'هانسو' => 16, 'فرم ۱۶' => 16, 'فرم 16' => 16, '16' => 16, '۱۶' => 16,
            'ایل یو' => 17, 'ایل‌یو' => 17, 'فرم ۱۷' => 17, 'فرم 17' => 17, '17' => 17, '۱۷' => 17,
        ];

        if (isset($map[$lower])) {
            return $map[$lower];
        }

        if (isset($map[$cleaned])) {
            return $map[$cleaned];
        }

        if (preg_match('/(\d+)/', $name, $matches)) {
            return (int) $matches[1];
        }

        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $converted = str_replace($persianDigits, $englishDigits, $name);
        if (preg_match('/(\d+)/', $converted, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public static function defaultNameForNumber(int $number): string
    {
        return config("poomsae.forms.{$number}") ?? ('Taegeuk '.$number);
    }
}
