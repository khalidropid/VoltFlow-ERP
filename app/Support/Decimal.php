<?php

namespace App\Support;

use InvalidArgumentException;

final class Decimal
{
    public const SCALE = 4;

    public static function normalize(string|int|float $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 4, '0');
    }

    public static function compare(string|int|float $a, string|int|float $b): int
    {
        $a = self::normalize($a); $b = self::normalize($b);
        [$aw, $af] = explode('.', $a); [$bw, $bf] = explode('.', $b);
        $aw = ltrim($aw, '0') ?: '0'; $bw = ltrim($bw, '0') ?: '0';
        if (strlen($aw) !== strlen($bw)) return strlen($aw) <=> strlen($bw);
        if ($aw !== $bw) return $aw <=> $bw;
        return $af <=> $bf;
    }

    public static function add(string|int|float $a, string|int|float $b): string
    {
        return self::fromScaled(self::addIntegers(self::scaled($a), self::scaled($b)));
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        if (self::compare($a, $b) < 0) throw new InvalidArgumentException('Decimal subtraction cannot produce a negative value.');
        return self::fromScaled(self::subIntegers(self::scaled($a), self::scaled($b)));
    }

    public static function multiplyByInteger(string|int|float $amount, int $quantity): string
    {
        if ($quantity < 0) throw new InvalidArgumentException('Quantity cannot be negative.');
        return self::fromScaled(self::mulInteger(self::scaled($amount), $quantity));
    }

    private static function scaled(string|int|float $value): string
    {
        return str_replace('.', '', self::normalize($value));
    }

    private static function fromScaled(string $value): string
    {
        $value = ltrim($value, '0') ?: '0';
        if (strlen($value) <= 4) $value = str_pad($value, 5, '0', STR_PAD_LEFT);
        return substr($value, 0, -4) . '.' . substr($value, -4);
    }

    private static function addIntegers(string $a, string $b): string
    {
        $i = strlen($a)-1; $j = strlen($b)-1; $carry=0; $out='';
        while ($i>=0 || $j>=0 || $carry) {
            $sum=$carry+($i>=0 ? ord($a[$i--])-48 : 0)+($j>=0 ? ord($b[$j--])-48 : 0);
            $out=($sum%10).$out; $carry=intdiv($sum,10);
        }
        return ltrim($out,'0') ?: '0';
    }

    private static function subIntegers(string $a, string $b): string
    {
        $i=strlen($a)-1; $j=strlen($b)-1; $borrow=0; $out='';
        while ($i>=0) {
            $x=(ord($a[$i--])-48)-$borrow; $y=$j>=0 ? ord($b[$j--])-48 : 0;
            if ($x<$y) {$x+=10; $borrow=1;} else $borrow=0;
            $out=($x-$y).$out;
        }
        return ltrim($out,'0') ?: '0';
    }

    private static function mulInteger(string $a, int $b): string
    {
        if ($b === 0 || $a === '0') return '0';
        $i=strlen($a)-1; $carry=0; $out='';
        while ($i>=0) {
            $v=(ord($a[$i--])-48)*$b+$carry;
            $out=($v%10).$out; $carry=intdiv($v,10);
        }
        while ($carry>0) {$out=($carry%10).$out; $carry=intdiv($carry,10);}
        return ltrim($out,'0') ?: '0';
    }
}
