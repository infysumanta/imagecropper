<?php

namespace Sumantablog\Cropper;

use Sumantablog\Admin\Form\Field\ImageField;
use Sumantablog\Admin\Form\Field\File;
use Sumantablog\Admin\Admin;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Crop extends File
{   
    use ImageField;

    private $ratioW = 100;

    private $ratioH = 100;

    protected $view = 'laravel-admin-cropper::cropper';

    protected static $css = [
        '/vendor/laravel-admin-ext/cropper/cropper.min.css',
    ];

    protected static $js = [
        '/vendor/laravel-admin-ext/cropper/cropper.min.js',
        '/vendor/laravel-admin-ext/cropper/layer/layer.js'
    ];

    protected function preview()
    {
        return $this->objectUrl($this->value());
    }

    /**
     * [Convert Base64 picture to local picture and save]
     * @E-mial wuliqiang_aa@163.com
     * @TIME   2017-04-07
     * @WEB    http://blog.iinu.com.cn
     * @param [Base64] $base64_image_content [Base64 to save]
     * @param [directory] $path [path to save]
     */
    private function base64_image_content($base64_image_content, $path)
    {
        //Match the format of the picture
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            if (!file_exists($path)) {
                //Check if there is this folder, if not, create it and give 755 permissions
                mkdir($path, 0755, true);
            }
            $filename = md5(microtime()) . ".{$type}";
            $all_path = $path . '/' . $filename;
            $content = base64_decode(str_replace($result[1], '', $base64_image_content));
            if (file_put_contents($all_path, $content)) {
                return ['path'=>$all_path, 'filename'=>$filename];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function prepare($base64)
    {
        if (empty($base64)) {
            $this->destroy();
            return $base64;
        } else if (preg_match('/data:image\/.*?;base64/is',$base64)) {
            //Check if it is base64 encoded
            //Base64 to image cache returns an absolute path
            $image = $this->base64_image_content($base64,public_path('uploads/base64img_cache'));
            if ($image !== false) {
                $image = new UploadedFile($image['path'],$image['filename']);
                $this->name = $this->getStoreName($image);
                $this->callInterventionMethods($image->getRealPath());
                $path = $this->uploadAndDeleteOriginal($image);
                return $path;
            } else {
                return 'lost';
            }
        } else {
            //Not base64 encoded
            return $base64;
        }
    }

    public function cRatio($width,$height)
    {
        if (!empty($width) and is_numeric($width)) {
            $this->attributes['data-w'] = $width;
        } else {
            $this->attributes['data-w'] = $this->ratioW;
        }
        if (!empty($height) and is_numeric($height)) {
            $this->attributes['data-h'] = $height;
        } else {
            $this->attributes['data-h'] = $this->ratioH;
        }
        return $this;
    }

    public function render()
    {
        $this->name = $this->formatName($this->column);
        $cTitle = trans("admin_cropper.title");
        $cDone = trans("admin_cropper.done");
        $cOrigin = trans("admin_cropper.origin");
        $cClear = trans("admin_cropper.clear");
        $script = <<<EOT

        //Pre-stored picture types

        function getMIME(url)
        {
            var preg = new RegExp('data:(.*);base64','i');
            var result = preg.exec(url);
            console.log(result)
            if (result != null) {
                return result[1];
            } else {
                var ext = url.substring(url.lastIndexOf(".") + 1).toLowerCase();
                return 'image/' + ext
            }
        }

        function cropper(imgSrc,cropperFileE)
        {
            var w = $(cropperFileE).attr('data-w');
            var h = $(cropperFileE).attr('data-h');
            var cropperImg = '<div id="cropping-div"><img id="cropping-img" src="'+imgSrc+'"><\/div>';
            //Generate elastic layer module
            layer.open({
                zIndex: 3000,
                type: 1,
                skin: 'layui-layer-demo', //Style class name
                area: ['800px', '600px'],
                closeBtn: 2, //第二种关闭按钮
                anim: 2,
                resize: false,
                shadeClose: false, //Close mask off
                title: '$cTitle',
                content: cropperImg,
                btn: ['$cDone','$cOrigin','$cClear'],
                btn1: function(){
                    var cas = cropper.getCroppedCanvas({
                        width: w,
                        height: h
                    });
                    //Crop data conversion base64
                    console.log(imgSrc)
                    var base64url = cas.toDataURL(getMIME(imgSrc));
                    //Replace preview
                    cropperFileE.nextAll('.cropper-img').attr('src',base64url);
                    //Replace submission data
                    cropperFileE.nextAll('.cropper-input').val(base64url);
                    //Destroy the cutter instance
                    cropper.destroy();
                    layer.closeAll('page');
                },
                btn2:function(){
                    //Close box by default
                    cropperFileE.nextAll('.cropper-img').attr('src',imgSrc);
                    //Replace submission data
                    cropperFileE.nextAll('.cropper-input').val(imgSrc);
                    //Destroy the cutter instance
                    cropper.destroy();
                },
                btn3:function(){
                    //Clear forms and options
                    //Destroy the cutter instance
                    cropper.destroy();
                    layer.closeAll('page');
                    cropperFileE.nextAll('.cropper-img').removeAttr('src');
                    cropperFileE.nextAll('.cropper-input').val('');
                    cropperFileE.val('');
                }
            });

            var image = document.getElementById('cropping-img');
            var cropper = new Cropper(image, {
                aspectRatio: w / h,
                viewMode: 2,
            });
        }

        $('form div').on('click','.cropper-btn',function(){
            $(this).nextAll('.cropper-file').click()
            return false;
        });

        $('form').on('change','.cropper-file',function(fileE){
            
            var file = $(this)[0].files[0];
            
            var reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(e){
             
                $(this).nextAll('.cropper-img').attr('src',e.target.result);
                
                cropper(e.target.result,$(fileE.target));
                
                $(this).nextAll('.cropper-input').val(e.target.result);
            };
            return false;
        });
        
        $('form').on('click','.cropper-img',function(){
            cropper($(this).attr('src'),$(this).prevAll('.cropper-file'));
            return false;
        });

EOT;

        if (!$this->display) {
            return '';
        }

        Admin::script($script);
        return view($this->getView(), $this->variables(),['preview'=>$this->preview()]);
    }

}
