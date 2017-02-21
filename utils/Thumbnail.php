<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


/**
 * 图片缩略类
 */

class Gek_Utils_Thumbnail {
    
    // 生成缩略图（Gmagick版）,参数图片类型: GIF PNG JPEG
    // 参数border指明是否将空白处以白色边框填充。
    public static function make($imageBlob, $thumbWidth, $thumbHeight, $imgType, $border=true)
    {
        try {
            if (strtoupper($imgType) == 'GIF') {
                 // 使用MagickWand扩展绕开Gmagick库扩展不能正确处理GIF格式图片的问题
                return self::makeByMagickWand($imageBlob, $thumbWidth, $thumbHeight, $imgType);
            } else if(strtoupper($imgType) == 'PNG') { // 检查png图片是否为透明背景
                $isTransparent = self::isTransparentByImagick($imageBlob); //会有一点性能影响
            } else {
                $isTransparent = false;
            }
       
            $image = new Gmagick();
            $image->readImageBlob($imageBlob);
           
            // 计算缩略图尺寸，并调整
            $w = $image->getImageWidth();
            $h = $image->getImageHeight();
            if(($thumbWidth > 0 && $thumbHeight > 0) && ($w > $thumbWidth || $h > $thumbHeight)) {
                if($w / $thumbWidth > $h / $thumbHeight) {
                    $tw = $thumbWidth;
                    $th = $h / ($w / $thumbWidth);
                } else {
                    $tw = $w / ($h / $thumbHeight);
                    $th = $thumbHeight;
                }

                // 调整图片尺寸为缩略图尺寸，并将空白处以白色边框填充
                $image->scaleImage($tw, $th);
                if($border) {
                    $image->borderImage('white', ($thumbWidth - $tw) / 2, ($thumbHeight - $th) / 2);
                }
            }
            
            // 如果不是透明背景的图片就转为jpeg格式进行压缩处理
            if(!$isTransparent) {
                $image->setImageFormat('JPEG');          // PNG图片不能去掉，会有性能影响
                //$quality = 75;                         // 设置压缩质量是有效的，比如680k 75=>380k 50=>280k，但不需要设置，默认已经设置为75
                //$image->setCompressionQuality($quality);//设置了可能会造成部分原压缩质量小于75的图片变大
                /* 下面Gmagick不支持。
                $image->setImageCompression(Gmagick::COMPRESSION_JPEG);
                $quality = $image->getImageCompressionQuality();
                if($quality >= 80) 
                    $quality = $quality * 0.75;
                else if ($quality == 0) 
                    $quality = 75;
                $image->setImageCompressionQuality($quality);*/
            }
            
            // 去掉图片里的exif和注释等信息
            $image->stripImage(); 
            
            // 取得处理后的图片内容，用作返回值，方便后续处理
            $resultImageBlob = $image->getImageBlob();
            $image->destroy();
            $image = NULL;
            return $resultImageBlob;
            
        } catch(GmagickException $e) {
            return false;
        }
    }
    
    //生成缩略图（MagickWand版）
    private static function makeByMagickWand($imageBlob, $thumbWidth, $thumbHeight, $imgType)
    {
        // 检查gif/png图片是否为透明背景
        if(strtoupper($imgType) == 'GIF' || strtoupper($imgType) == 'PNG') {
            $isTransparent = self::isTransparentByImagick($imageBlob);
        } else {
            $isTransparent = false;
        }
        
        $image = NewMagickWand();
        if (!MagickReadImageBlob($image, $imageBlob)) {
            return FALSE;
        }
        
        #计算缩略图尺寸，并调整
        $w = MagickGetImageWidth($image);
        $h = MagickGetImageHeight($image);
        if(($thumbWidth > 0 && $thumbHeight > 0) && ($w > $thumbWidth || $h > $thumbHeight)) {
            if($w / $thumbWidth > $h / $thumbHeight) {
                $tw = $thumbWidth;
                $th = $h / ($w / $thumbWidth);
            } else {
                $tw = $w / ($h / $thumbHeight);
                $th = $thumbHeight;
            }

            #调整图片尺寸为缩略图尺寸，并将空白处以白色边框填充
            #MagickSampleImage($image, $tw, $th);
            MagickScaleImage($image, $tw, $th);
            #MagickResizeImage($image, $tw, $th, MW_LanczosFilter, 1);
            #MagickBorderImage($image, 'white', ($thumbWidth - $tw) / 2, ($thumbHeight - $th) / 2); //该函数对部分jpeg图片失效，会造成程序退出
        }
        
        // 如果不是透明背景的图片就转为jpeg格式进行压缩处理
        if(!$isTransparent) {
            MagickSetImageFormat($image, 'JPEG');
            /*
            MagickSetImageCompression($image, MW_JPEGCompression);
            $quality = MagickGetImageCompressionQuality($image);
            if($quality >= 80) 
                $quality = $quality * 0.75;
            else if($quality == 0) 
                $quality = 75;
            MagickSetImageCompressionQuality($image, $quality); */
        }

        // 去掉图片里的exif和注释等信息
        MagickStripImage($image);

        #取得处理后的图片内容，用作返回值，方便后续处理
        $resultImageBlob = MagickGetImageBlob($image);
        DestroyMagickWand($image);
        $image = NULL;
        return $resultImageBlob;
   }
   
    // 检查图片背景是否透明，gif、png支持透明背景、jpeg不支持。 Gmagick和MagicWand扩展不支持。
    private static function isTransparentByImagick($imageBlob)
    {
        try {
            $image = new Imagick();
            $image->readImageBlob($imageBlob);
            $flag = $image->getImageAlphaChannel();
            if($flag == Imagick::ALPHACHANNEL_UNDEFINED  || $flag == Imagick::ALPHACHANNEL_DEACTIVATE) {
                $image->destroy();
                $image = NULL;
                return false;
            } else {
                $image->destroy();
                $image = NULL;
                return true;
            }
        } catch(ImagickException $e) {
            return false;
        }
    }
}
