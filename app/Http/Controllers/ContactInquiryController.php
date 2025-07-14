<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use App\Models\School;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\ContactInquiry\ContactInquiryInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Services\BootstrapTableService;

class ContactInquiryController extends Controller
{
    protected $contactInquiry;

    public function __construct(ContactInquiryInterface $contactInquiry)
    {
        $this->contactInquiry = $contactInquiry;
    }

    public function index()
    {
        ResponseService::noPermissionThenRedirect('contact-inquiry-list');
        
        // Check and set the correct database connection if user is School Admin
        $this->setSchoolDatabase();
        
        return view('contact-inquiry.index');
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenRedirect('contact-inquiry-list');
        
        // Check and set the correct database connection if user is School Admin
        $this->setSchoolDatabase();
        
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        
        // Normalize the showDeleted parameter to a boolean
        // The bootstrap table sends this as a string - "0" or "1"
        $showDeleted = in_array(request('show_deleted', 0), [true, 1, '1', 'true'], true);

        if(Auth::user() && Auth::user()->hasRole('Super Admin')) {
            $sql = $this->contactInquiry->builder();
        } else {
            $sql = $this->contactInquiry->builder();
        }
        
        // Apply the onlyTrashed filter if needed
        if ($showDeleted) {
            $sql = $sql->onlyTrashed();
        }
        
        if (!empty($request->status)) {
            $sql->where('status', $request->status);
        }

        if (!empty($request->search)) {
            $sql->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', "%$request->search%")
                ->orWhere('email', 'LIKE', "%$request->search%")
                ->orWhere('subject', 'LIKE', "%$request->search%")
                ->orWhere('message', 'LIKE', "%$request->search%");
            });
        }

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($res as $row) {
            $operate = '';
            
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            
            // Determine which buttons to show based on delete status
            if ($showDeleted) {
                $operate = BootstrapTableService::restoreButton(route('contact-inquiry.restore', $row->id));
                $operate .= BootstrapTableService::deleteButton(route('contact-inquiry.destroy', $row->id));
            } else {
                $operate .= BootstrapTableService::trashButton(route('contact-inquiry.trash', $row->id));
            }

            $tempRow['operate'] = $operate;
            
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
    
    /**
     * Set the database connection for School Admin
     */
    private function setSchoolDatabase()
    {
        if (Auth::check() && Auth::user()->hasRole('School Admin')) {
            $school = Auth::user()->school;
            if ($school) {
                $school = School::find($school->id);
                Config::set('database.connections.school.database', $school->database_name);
                DB::purge('school');
                DB::connection('school')->reconnect();
                DB::setDefaultConnection('school');
            }
        } else {
            DB::setDefaultConnection('mysql');
        }
    }
    
    /**
     * Soft delete the specified contact inquiry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function trash($id)
    {
        // Check and set the correct database connection if user is School Admin
        $this->setSchoolDatabase();
        
        try {
            $this->contactInquiry->builder()->where('id', $id)->delete();
              
            ResponseService::successResponse('Contact inquiry moved to trash successfully');
        } catch (\Exception $e) {
            ResponseService::errorResponse('Error deleting contact inquiry: ' . $e->getMessage());
        }
    }

    /**
     * Restore a soft deleted contact inquiry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function restore($id)
    {
        // Check and set the correct database connection if user is School Admin
        $this->setSchoolDatabase();
        
        try {
            $this->contactInquiry->builder()->onlyTrashed()->where('id', $id)->restore();
                    
            ResponseService::successResponse('Contact inquiry restored successfully');
        } catch (\Exception $e) {
            ResponseService::errorResponse('Error restoring contact inquiry: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a soft deleted contact inquiry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function forceDelete($id)
    {
        // Check and set the correct database connection if user is School Admin
        $this->setSchoolDatabase();
        
        try {
            $this->contactInquiry->builder()->onlyTrashed()->where('id', $id)->forceDelete();
            
            ResponseService::successResponse('Contact inquiry permanently deleted successfully');
        } catch (\Exception $e) {
            ResponseService::errorResponse('Error permanently deleting contact inquiry: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        // Check and set the correct database connection if user is School Admin
        $this->setSchoolDatabase();
        
        try {
            // Check if show_deleted is true (we're in the trash view)
            $showDeleted = request('show_deleted');
            
            if (!$showDeleted) {
                $this->contactInquiry->builder()->onlyTrashed()->where('id', $id)->forceDelete();
                $message = 'Contact inquiry permanently deleted successfully';
            } else {
                // Regular delete (soft delete)
                $this->contactInquiry->builder()->where('id', $id)->delete();              
                $message = 'Contact inquiry moved to trash successfully';
            }
            
            ResponseService::successResponse($message);
        } catch (\Exception $e) {
           ResponseService::errorResponse('Error processing contact inquiry: ' . $e->getMessage());
        }
    }
    
}