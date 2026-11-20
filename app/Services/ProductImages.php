<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\ProductException;
final class ProductImages
{
    /** Decode and re-encode uploads so only bounded, valid raster images are stored. */
    public static function prepare(?array $upload): ?string
    {
        if(!$upload || ($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
        if(($upload['error']??-1)!==UPLOAD_ERR_OK || !is_string($upload['tmp_name']??null) || !is_uploaded_file($upload['tmp_name']))throw new ProductException(['image'=>'The image could not be uploaded. Choose a JPEG, PNG or WebP under 800 KB.']);
        if(($upload['size']??0)<1 || $upload['size']>819200)throw new ProductException(['image'=>'Choose an image under 800 KB.']);
        $info=@getimagesize($upload['tmp_name']);
        if(!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true) || $info[0]<1 || $info[1]<1 || $info[0]>4000 || $info[1]>4000 || $info[0]*$info[1]>12000000)throw new ProductException(['image'=>'Choose a valid JPEG, PNG or WebP image up to 4000 pixels wide and high.']);
        $raw=file_get_contents($upload['tmp_name']);$source=$raw===false?false:@imagecreatefromstring($raw);
        if(!$source)throw new ProductException(['image'=>'The image could not be read. Choose another image.']);
        $scale=min(1,800/max($info[0],$info[1]));$width=max(1,(int)round($info[0]*$scale));$height=max(1,(int)round($info[1]*$scale));
        $image=imagecreatetruecolor($width,$height);$white=imagecolorallocate($image,255,255,255);imagefill($image,0,0,$white);imagecopyresampled($image,$source,0,0,0,0,$width,$height,$info[0],$info[1]);
        ob_start();$ok=imagejpeg($image,null,85);$data=ob_get_clean();imagedestroy($source);imagedestroy($image);
        if(!$ok || !is_string($data))throw new ProductException(['image'=>'The image could not be processed.']);
        return $data;
    }
    private static function path(string $id): string
    {
        if(!preg_match('/^[1-9][0-9]{0,18}$/D',$id))throw new \InvalidArgumentException('Invalid product ID.');
        return APP_ROOT.'/storage/product-images/'.$id.'.jpg';
    }
    public static function exists(string $id): bool{return is_file(self::path($id));}
    public static function saveNew(string $id,string $data): bool
    {
        $path=self::path($id);$dir=dirname($path);
        if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir))return false;
        $handle=@fopen($path,'x+b');if(!$handle)return false;
        $written=fwrite($handle,$data);$ok=$written===strlen($data) && fflush($handle);fclose($handle);
        if(!$ok){@unlink($path);return false;}return true;
    }
    public static function send(string $id): void
    {
        $path=self::path($id);if(!is_file($path)){http_response_code(404);return;}
        header('Content-Type: image/jpeg');header('Content-Length: '.filesize($path));readfile($path);
    }
}
