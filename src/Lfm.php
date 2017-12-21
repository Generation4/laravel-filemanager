<?php

namespace UniSharp\LaravelFilemanager;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;

class Lfm
{
    const PACKAGE_NAME = 'laravel-filemanager';
    const DS = '/';

    protected $config;
    protected $request;

    public function __construct(Config $config = null, Request $request = null)
    {
        $this->config = $config;
        $this->request = $request;
    }

    public function getStorage($storage_path)
    {
        if ($this->config->get('lfm.driver') === 'storage') {
            return new LfmStorageRepository($storage_path, $this->config->get('lfm.disk'));
        } else {
            return new LfmFileRepository($storage_path);
        }
    }

    public function input($key)
    {
        return $this->translateFromUtf8($this->request->input($key));
    }

    /**
     * Get only the file name.
     *
     * @param  string  $path  Real path of a file.
     * @return string
     */
    public function getNameFromPath($path)
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    public function allowFolderType($type)
    {
        if ($type == 'user') {
            return $this->allowMultiUser();
        } else {
            return $this->allowShareFolder();
        }
    }

    public function getCategoryName()
    {
        $type = $this->currentLfmType();

        return $this->config->get('lfm.folder_categories.' . $type . '.folder_name', 'files');
    }

    /**
     * Get current lfm type.
     *
     * @return string
     */
    public function currentLfmType()
    {
        $lfm_type = 'file';

        $request_type = lcfirst(str_singular($this->input('type')));
        $available_types = array_keys($this->config->get('lfm.folder_categories'));

        if (in_array($request_type, $available_types)) {
            $lfm_type = $request_type;
        }

        return $lfm_type;
    }

    public function getDisplayMode()
    {
        $type_key = $this->currentLfmType();
        $startup_view = config('lfm.folder_categories.' . $type_key . '.startup_view');

        $view_type = 'grid';
        $target_display_type = $this->input('show_list') ?: $startup_view;

        if (in_array($target_display_type, ['list', 'grid'])) {
            $view_type = $target_display_type;
        }

        return $view_type;
    }

    public function getUserSlug()
    {
        $config = $this->config->get('lfm.user_folder_name');

        if (is_callable($config)) {
            return call_user_func($config);
        }

        if (class_exists($config)) {
            return app()->make($config)->userField();
        }

        return empty(auth()->user()) ? '' : auth()->user()->$config;
    }

    public function getRootFolder($type = null)
    {
        if (is_null($type)) {
            $type = 'share';
            if ($this->allowFolderType('user')) {
                $type = 'user';
            }
        }

        if ($type === 'user') {
            $folder = $this->getUserSlug();
        } else {
            $folder = $this->config->get('lfm.shared_folder_name');
        }

        $ds = static::DS;
        if ($this->isRunningOnWindows()) {
            $ds = '\\';
        }

        return $ds . $folder;
    }

    public function getThumbFolderName()
    {
        return $this->config->get('lfm.thumb_folder_name');
    }

    public function getFileIcon($ext)
    {
        return $this->config->get("lfm.file_icon_array.{$ext}", 'fa-file-o');
    }

    public function getFileType($ext)
    {
        return $this->config->get("lfm.file_type_array.{$ext}", 'File');
    }

    public function availableMimeTypes()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.valid_mime');
    }

    public function maxUploadSize()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.max_size');
    }

    // TODO: do not use url function, and add test
    public function url($path = '')
    {
        return '/' . $path;
        // return url($path);
    }

    /**
     * Check if users are allowed to use their private folders.
     *
     * @return bool
     */
    public function allowMultiUser()
    {
        return $this->config->get('lfm.allow_multi_user') === true;
    }

    /**
     * Check if users are allowed to use the shared folder.
     * This can be disabled only when allowMultiUser() is true.
     *
     * @return bool
     */
    public function allowShareFolder()
    {
        if (! $this->allowMultiUser()) {
            return true;
        }

        return $this->config->get('lfm.allow_share_folder') === true;
    }

    /**
     * Translate file name to make it compatible on Windows.
     *
     * @param  string  $input  Any string.
     * @return string
     */
    public function translateFromUtf8($input)
    {
        if ($this->isRunningOnWindows()) {
            $input = iconv('UTF-8', mb_detect_encoding($input), $input);
        }

        return $input;
    }

    /**
     * Check current operating system is Windows or not.
     *
     * @return bool
     */
    public function isRunningOnWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    public function shouldSetStorageRoute()
    {
        $driver = $this->config->get('lfm.driver');

        if ($driver === 'file') {
            return false;
        }

        $storage_root = $this->getStorage('/')->rootPath();

        if ($driver === 'storage' && (ends_with($storage_root, 'public') || ends_with($storage_root, 'public/'))) {
            return false;
        }

        return true;
    }

    /**
     * Shorter function of getting localized error message..
     *
     * @param  mixed  $error_type  Key of message in lang file.
     * @param  mixed  $variables   Variables the message needs.
     * @return string
     */
    public function error($error_type, $variables = [])
    {
        throw new \Exception(trans(self::PACKAGE_NAME . '::lfm.error-' . $error_type, $variables));
    }
}