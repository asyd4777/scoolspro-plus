<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;
use ZipArchive;

class SystemUpdateController extends Controller {
    private string $destinationPath;
    private CachingService $cache;

    public function __construct(CachingService $cachingService) {
        $this->destinationPath = base_path() . '/update/tmp/';
        $this->cache = $cachingService;
    }

    public function index() {
        if (!Auth::user()->hasRole('Super Admin')) {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return redirect(route('home'))->withErrors($response);
        }
        $system_version = SystemSetting::where('name', 'system_version')->first();
        return view('system-update.index', compact('system_version'));
    }

    public function update(Request $request) {
        if (!Auth::user()->hasRole('Super Admin')) {
            $response = array(
                'error'   => true,
                'message' => trans('no_permission_message')
            );
            return response()->json($response);
        }

        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:zip',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            
            if (!is_dir($this->destinationPath) && !mkdir($concurrentDirectory = $this->destinationPath, 0777, true) && !is_dir($concurrentDirectory)) {
                ResponseService::errorResponse("Permission Error while creating Temp Directory");
            }

            
            $zipfile = $request->file('file');
            $fileName = $zipfile->getClientOriginalName();
            $zipfile->move($this->destinationPath, $fileName);
            $target_path = base_path() . DIRECTORY_SEPARATOR;

            
            $zip = new ZipArchive();
            $filePath = $this->destinationPath . '/' . $fileName;
            $zipStatus = $zip->open($filePath);
            if ($zipStatus !== true) {
                ResponseService::errorResponse('something_wrong_try_again');
            }

            $zip->extractTo($this->destinationPath);
            $zip->close();
            unlink($filePath);

            
            $ver_file = $this->destinationPath . 'version_info.php';
            $source_path = $this->destinationPath . 'source_code.zip';
            if (!file_exists($ver_file) || !file_exists($source_path)) {
                ResponseService::errorResponse('Zip File is not Uploaded to Correct Path');
            }

            
            $ver_file1 = $target_path . 'version_info.php';
            $source_path1 = $target_path . 'source_code.zip';
            if (!rename($ver_file, $ver_file1) || !rename($source_path, $source_path1)) {
                ResponseService::errorResponse('Error Occurred while moving a Zip File');
            }

            
            $version_file = require($ver_file1);

            
            $current_version = SystemSetting::where('name', 'system_version')->first();
            if (!$current_version) {
                unlink($ver_file1);
                unlink($source_path1);
                ResponseService::errorResponse('Current system version not found');
            }
            $current_version = $current_version['data'];

            
            if ($current_version != $version_file['update_version']) {
                if ($current_version != $version_file['current_version']) {
                    unlink($ver_file1);
                    unlink($source_path1);
                    ResponseService::errorResponse($current_version . ' ' . trans('Please update nearest version first'));
                }
            }

           
            $zip1 = new ZipArchive();
            $zipFile1 = $zip1->open($source_path1);
            if ($zipFile1 !== true) {
                unlink($ver_file1);
                unlink($source_path1);
                ResponseService::errorResponse('Source Code Zip Extraction Failed');
            }

            $zip1->extractTo($target_path);
            $zip1->close();

            
            Artisan::call('migrate');
            Artisan::call('db:seed --class=InstallationSeeder');

            
            unlink($source_path1);
            unlink($ver_file1);

            
            SystemSetting::where('name', 'system_version')->update([
                'data' => $version_file['update_version']
            ]);

            
            $wizardSettings = [
                [
                    'name' => 'wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'system_settings_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'notification_settings_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'email_settings_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'verify_email_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'email_template_settings_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'payment_settings_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ],
                [
                    'name' => 'third_party_api_settings_wizard_checkMark',
                    'data' => 1,
                    'type' => 'integer'
                ]
            ];

            SystemSetting::upsert($wizardSettings, ["name"], ["data", "type"]);

            
            $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.SETTINGS'));
            ResponseService::successResponse('System Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse('An error occurred during the update process');
        }
    }
}