<?php

namespace App\Http\Controllers;

use App\Repositories\FeesType\FeesTypeInterface;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\SessionYearsTrackingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Throwable;

class FeesTypeController extends Controller {
    private FeesTypeInterface $feesType;
    private SessionYearsTrackingsService $sessionYearsTrackingsService;
    private CachingService $cache;

    public function __construct(FeesTypeInterface $feesType, SessionYearsTrackingsService $sessionYearsTrackingsService, CachingService $cache) {
        $this->feesType = $feesType;
        $this->sessionYearsTrackingsService = $sessionYearsTrackingsService;
        $this->cache = $cache;
    }

    public function index() {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-type-list');
        return view('fees.fees_types');
    }

    public function store(Request $request) {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-type-create');
        try {
            DB::beginTransaction();
            $feesType = $this->feesType->create($request->except('_token'));
            $sessionYear = $this->cache->getDefaultSessionYear();
            $semester = $this->cache->getDefaultSemesterData();
            if ($semester) {
                $this->sessionYearsTrackingsService->storeSessionYearsTracking('App\Models\FeesType', $feesType->id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, $semester->id);
            } else {
                $this->sessionYearsTrackingsService->storeSessionYearsTracking('App\Models\FeesType', $feesType->id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, null);
            }
            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, "FeesTypeController -> store method");
            ResponseService::errorResponse();
        }
    }

    public function show() {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-type-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');
        $showDeleted = request('show_deleted');

        $sql = $this->feesType->builder()->with('session_years_trackings')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('name', 'LIKE', "%$search%")
                        ->orwhere('description', 'LIKE', "%$search%")
                        ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%");
                });
            })
            ->when(!empty($showDeleted), function ($query) {
                $query->onlyTrashed();
            });

        $sessionYear = $this->cache->getDefaultSessionYear();
        $sql->whereHas('session_years_trackings', function ($q) use ($sessionYear) {
            $q->where('session_year_id', $sessionYear->id);
        });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            if ($showDeleted) {
                $operate = BootstrapTableService::restoreButton(route('fees-type.restore', $row->id));
                $operate .= BootstrapTableService::trashButton(route('fees-type.trash', $row->id));
            } else {
                $operate = BootstrapTableService::editButton(route('fees-type.update', $row->id));
                $operate .= BootstrapTableService::deleteButton(route('fees-type.destroy', $row->id));
            }
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function update(Request $request, $id) {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-type-edit');
        try {
            $this->feesType->update($id, [
                'name'        => $request->edit_name,
                'description' => $request->edit_description,
            ]);
            ResponseService::successResponse("Data Updated Successfully");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "FeesTypeController -> Update method");
            ResponseService::errorResponse();
        }
    }

    public function destroy($id) {
        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-type-delete');
        try {
            $this->feesType->deleteById($id);
            // $sessionYear = $this->cache->getDefaultSessionYear();
            // $semester = $this->cache->getDefaultSemesterData();
            // if ($semester) {
            //     $this->sessionYearsTrackingsService->deleteSessionYearsTracking('App\Models\FeesType', $id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, $semester->id);
            // } else {
            //     $this->sessionYearsTrackingsService->deleteSessionYearsTracking('App\Models\FeesType', $id, Auth::user()->id, $sessionYear->id, Auth::user()->school_id, null);
            // }
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "FeesTypeController -> destroy method");
            ResponseService::errorResponse();
        }
    }

    public function restore(int $id) {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noAnyPermissionThenRedirect(['fees-type-delete']);
        try {
            $this->feesType->findOnlyTrashedById($id)->restore();
            ResponseService::successResponse("Data Restored Successfully");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "FeesTypeController -> restore method");
            ResponseService::errorResponse();
        }
    }

    public function trash($id) {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-type-delete');
        try {
            $this->feesType->findOnlyTrashedById($id)->forceDelete();
            ResponseService::successResponse("Data Deleted Permanently");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "FeesTypeController -> trash method");
            ResponseService::errorResponse();
        }
    }
}
