<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Dominik Zogg 2012
 * @author     Dominik Zogg <dominik.zogg@gmail.com>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

new download();

class download
{
    /**
     * @var string documentroot
     */
    protected $_strDocumentRoot = '';

    /**
     * @var string path
     */
    protected $_strPath = '';

    /**
     * @var string dirname
     */
    protected $_strDirName = '';

    /**
     * @var string basename
     */
    protected $_strBaseName = '';

    /**
     * @var string filename
     */
    protected $_strFileName = '';

    /**
     * @var string extension
     */
    protected $_strExtension = '';

    /**
     * @var string mime
     */
    protected $_strMime = '';

    /**
     * @var int size
     */
    protected $_intSize = 0;

    /**
     * @var string expire
     */
    protected $_strExpire = '';

    /**
     * @var string lastmodification
     */
    protected $_strLastModification = '';

    /**
     * @var string etag
     */
    protected $_strETag = '';

    /**
     * @var string requestlastmodification
     */
    protected $_strRequestLastModification = '';

    /**
     * @var string requestetag
     */
    protected $_strRequestETag = '';

    /**
     * __construct
     */
    public function __construct()
    {
        // request
        $this->_request();

        // check fileexist
        $this->_checkFileExists();

        // check permission
        $this->_checkPermission();

        // set fileinfo
        $this->_setFileinfo();

        // return file
        $this->_reponseFile();
    }

    /**
     * _request
     */
    protected function _request()
    {
        // documentroot
        $strDocumentRoot = substr($_SERVER['DOCUMENT_ROOT'], -1)  == '/' ? substr($_SERVER['DOCUMENT_ROOT'], 0, -1) : $_SERVER['DOCUMENT_ROOT'];

        // path
        $strPath = strpos($_SERVER['REQUEST_URI'], '?') !== false ? urldecode(substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'))) : urldecode($_SERVER['REQUEST_URI']);

        // set path parts
        $this->_setPath($strDocumentRoot, $strPath);

        // set requestlastmodifcation
        $this->_strRequestLastModification = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : '';

        // set requestetag
        $this->_strRequestETag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';
    }

    /**
     * _setPath
     * @param string $strDocumentRoot
     * @param string $strPath
     */
    protected function _setPath($strDocumentRoot, $strPath)
    {
        // set document root
        $this->_strDocumentRoot = $strDocumentRoot;

        // set path
        $this->_strPath = $strPath;

        // get pathinfo
        $arrPathInfo = pathinfo($this->_strPath);

        // set dirname
        $this->_strDirName = $arrPathInfo['dirname'];

        // set basename
        $this->_strBaseName = $arrPathInfo['basename'];

        // set filename
        $this->_strFileName = $arrPathInfo['filename'];

        // set extension
        $this->_strExtension = isset($arrPathInfo['extension']) ? $arrPathInfo['extension'] : '';
    }

    /**
     * _checkFileExists
     */
    protected function _checkFileExists()
    {
        if(!is_file($this->_strDocumentRoot . $this->_strPath))
        {
                // reponse
                $this->_reponse('File not found!', 404);
        }
    }

    /**
     * _checkPermission
     */
    protected function _checkPermission()
    {
        // check if a .htacces file exists
        if(is_file($this->_strDocumentRoot . $this->_strDirName . '/.htaccess'))
        {
            // get content of htaccess
            $arrHtaccessContent = explode("\n", file_get_contents($this->_strDocumentRoot . $this->_strDirName . '/.htaccess'));

            // if the permission order is deny first and is set deny from all, permit access
            if(in_array('order deny,allow', $arrHtaccessContent) && in_array('deny from all', $arrHtaccessContent))
            {
                // reponse
                $this->_reponse('This folder is secured by a .htaccess file!', 403);
            }
        }
    }

    /**
     * _setFileinfo
     */
    protected function _setFileinfo()
    {
        // set mime
        $strContentType = mime_content_type($this->_strDocumentRoot . $this->_strPath);
        $this->_strMime = substr($strContentType, 0, strpos($strContentType, '/') + 1) . $this->_strExtension;

        // set size
        $this->_intSize = filesize($this->_strDocumentRoot . $this->_strPath);

        // set expire
        $objDatetime = new DateTime('now', new DateTimeZone('UTC'));
        $objDatetime->modify('+1 month');
        $this->_strExpire = $objDatetime->format('D, d M Y H:i:s') .' GMT';

        // set lastmodification
        $objDatetime = new DateTime('@'. filemtime($this->_strDocumentRoot . $this->_strPath), new DateTimeZone('UTC'));
        $this->_strLastModification = $objDatetime->format('D, d M Y H:i:s') .' GMT';

        // set etag
        $this->_strETag = md5_file($this->_strDocumentRoot . $this->_strPath);
    }

    /**
     * _reponseFile
     */
    protected function _reponseFile()
    {
        // if lastmodification and etag is equal to the browser cace
        if($this->_strLastModification == $this->_strRequestLastModification && $this->_strETag == $this->_strRequestETag)
        {
            $this->_reponse('', 304);
        }

        // prepare headers
        $arrHeaders = array
        (
            'Pragma' => 'public',
            'Cache-Control' => 'public, must-revalidate',
            'Expires' => $this->_strExpire,
            'Last-Modified' => $this->_strLastModification,
            'ETag' => $this->_strETag,
            'Content-Type' => $this->_strMime,
            'Content-Length' => $this->_intSize,
            'Content-Disposition' => 'inline; filename="' . $this->_strBaseName . '"'
        );

        // reponse
        $this->_reponse(file_get_contents($this->_strDocumentRoot . $this->_strPath), 200, $arrHeaders);
    }

    /**
     * _reponse
     * @param string $strResponse reponse
     * @param int $intCode responsecode
     * @param array $arrHeaders headers
     */
    protected function _reponse($strResponse, $intCode = 200, $arrHeaders = array())
    {
        // prepare code string
        switch($intCode)
        {
            case 304:
                $strCode = '304 Not Modified';
                break;
            case 403:
                $strCode = '403 Forbidden';
                break;
            case 404:
                $strCode = '404 Not Found';
                break;
            default:
                $strCode = '200 OK';
        }

        // return code
        header("HTTP/1.1 {$strCode}");

        // headers
        foreach($arrHeaders as $strKey => $strValue)
        {
            header("{$strKey}: {$strValue}");
        }

        // clean output buffer
        ob_clean();

        // return content
        print($strResponse);

        // terminate script
        exit();
    }
}