<?php

namespace App\Support;

use InvalidArgumentException;

final class Decimal
{
    public const SCALE = 4;

    public static function normalize(string|int $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) throw new InvalidArgumentException('Invalid decimal value.');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 4, '0');
    }

    public static function compare(string|int $a, string|int $b): int
    {
        $a=self::normalize($a); $b=self::normalize($b);
        [$aw,$af]=explode('.',$a); [$bw,$bf]=explode('.',$b);
        $aw=ltrim($aw,'0')?:'0'; $bw=ltrim($bw,'0')?:'0';
        if(strlen($aw)!==strlen($bw)) return strlen($aw)<=>strlen($bw);
        if($aw!==$bw) return $aw<=>$bw;
        return $af<=>$bf;
    }

    public static function add(string|int $a,string|int $b):string
    { return self::fromScaled(self::addIntegers(self::scaled($a),self::scaled($b))); }

    public static function sub(string|int $a,string|int $b):string
    {
        if(self::compare($a,$b)<0) throw new InvalidArgumentException('Decimal subtraction cannot produce a negative value.');
        return self::fromScaled(self::subIntegers(self::scaled($a),self::scaled($b)));
    }

    public static function multiply(string|int $a,string|int $b):string
    {
        $product=self::mulStrings(self::scaled($a),self::scaled($b));
        $product=strlen($product)>4 ? substr($product,0,-4) : '0';
        return self::fromScaled($product);
    }

    private static function scaled(string|int $value):string
    { return str_replace('.','',self::normalize($value)); }

    private static function fromScaled(string $value):string
    {
        $value=ltrim($value,'0')?:'0';
        if(strlen($value)<=4) $value=str_pad($value,5,'0',STR_PAD_LEFT);
        return substr($value,0,-4).'.'.substr($value,-4);
    }

    private static function addIntegers(string $a,string $b):string
    {
        $i=strlen($a)-1;$j=strlen($b)-1;$carry=0;$out='';
        while($i>=0||$j>=0||$carry){$sum=$carry+($i>=0?ord($a[$i--])-48:0)+($j>=0?ord($b[$j--])-48:0);$out=($sum%10).$out;$carry=intdiv($sum,10);}
        return ltrim($out,'0')?:'0';
    }

    private static function subIntegers(string $a,string $b):string
    {
        $i=strlen($a)-1;$j=strlen($b)-1;$borrow=0;$out='';
        while($i>=0){$x=ord($a[$i--])-48-$borrow;$y=$j>=0?ord($b[$j--])-48:0;if($x<$y){$x+=10;$borrow=1;}else{$borrow=0;}$out=($x-$y).$out;}
        return ltrim($out,'0')?:'0';
    }

    private static function mulStrings(string $a,string $b):string
    {
        if($a==='0'||$b==='0') return '0';
        $result=array_fill(0,strlen($a)+strlen($b),0);
        for($i=strlen($a)-1;$i>=0;$i--) {
            for($j=strlen($b)-1;$j>=0;$j--) {
                $p=$i+$j+1;
                $result[$p]+=(ord($a[$i])-48)*(ord($b[$j])-48);
            }
        }
        for($i=count($result)-1;$i>0;$i--){$result[$i-1]+=intdiv($result[$i],10);$result[$i]%=10;}
        return ltrim(implode('',$result),'0')?:'0';
    }
}
