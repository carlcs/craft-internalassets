<?php
/**
* Internal Assets plugin for Craft CMS 3.x
*
* A simple plugin to restrict access to assets for permitted users only. Access to a given asset is only granted if the user has view-permissions for the given source (this can be set in the user- or group-settings). The asset source folder should be moved out of the web root folder so the files are never accessible without this plugin.
*
* @link      https://github.com/tikiatua
* @copyright Copyright (c) 2018 Ramon Saccilotto
*/

namespace saccilottoconsulting\internalassets\controllers;

use saccilottoconsulting\internalassets\InternalAssets;

use Craft;
use craft\web\Controller;
use craft\helpers\FileHelper;
use yii\web\HttpException;

/**
* Default Controller
*
* Generally speaking, controllers are the middlemen between the front end of
* the CP/website and your plugin’s services. They contain action methods which
* handle individual tasks.
*
* A common pattern used throughout Craft involves a controller action gathering
* post data, saving it on a model, passing the model off to a service, and then
* responding to the request appropriately depending on the service method’s response.
*
* Action methods begin with the prefix “action”, followed by a description of what
* the method does (for example, actionSaveIngredient()).
*
* https://craftcms.com/docs/plugins/controllers
*
* @author    Ramon Saccilotto
* @package   InternalAssets
* @since     2.0.0
*/
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
    * @var    bool|array Allows anonymous access to this controller's actions.
    *         The actions must be in 'kebab-case'
    * @access protected
    */
    protected $allowAnonymous = ['fetch'];

    /**
    * Handle access requests with directory name and file name
    *
    * @return mixed
    */
    public function actionFetch(string $path)
    {

        // find the volume by the given path
        $volumes = Craft::$app->getVolumes();
        $publicVolumes = $volumes->getPublicVolumes();

        $targetVolume = null;
        $volumeUrl = null;
        foreach ($publicVolumes as $volume) {

            // get the public url of the volume
            $volumeUrl = $volume['url'];

            // strip any prefixing slashes
            $volumeUrl = ltrim($volumeUrl, '/');
            $volumeUrl = ltrim($volumeUrl, '//');
            $volumeUrl = rtrim($volumeUrl, '/');

            if (strpos($path, $volumeUrl.'/') === 0) {
                $targetVolume = $volume;
                break;
            }

        }

        if ($targetVolume === null) {
            throw new HttpException(404, "Sorry. File not found or permission denied");
            return;
        }

        // get the current user session
        $currentUser = Craft::$app->getUser();

        $accessGranted = $currentUser->getIsAdmin();

        if ($accessGranted === false && isset($volume['uid'])) {

            // viewvolume permissions are required to access the volume
            // note: check for volume permission was changed from id to uid
            // thanks to @jackgringo for the fix.
            $volumePermission = 'viewvolume:' . $volume['uid'];

            // check if the user is allowed to access the volume
            $accessGranted = $currentUser->checkPermission($volumePermission);
        }


        // fallback to check permission with volume id instead of uid
        if ($accessGranted === false && isset($volume['id'])) {
            $volumePermission = 'viewvolume:' . $volume['id'];
            $accessGranted = $currentUser->checkPermission($volumePermission);
        }

        // permissions are still not granted
        if ($accessGranted === false) {
            Craft::$app->controller->requireLogin();

            throw new HttpException(404, "Sorry. File not found or permission denied");
            return;
        }

        // generate the filesystem path to the file
        $filepath = ltrim($path, $volumeUrl);
        $filepath = Craft::getAlias($volume['path']) . $filepath;
        $filepath = FileHelper::normalizePath($filepath);

        // get only the filename
        $filename = basename($filepath);

        // get the files mime type
        $mimeType = FileHelper::getMimeTypeByExtension($filename);

        $inline = $mimeType === 'application/pdf';

        return Craft::$app->getResponse()->sendFile($filepath, $filename, [
            'mimeType' => $mimeType,
            'inline' => $inline,
        ]);
    }

}
