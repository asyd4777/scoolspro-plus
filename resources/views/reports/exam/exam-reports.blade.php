@extends('layouts.master')

@section('title')
    {{ __('exam') . ' ' . __('reports') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('exam') . ' ' . __('reports') }}
            </h3>
        </div>
        <div class="row">
            
            <!-- Right Details Tabs -->
            <div class="col-md-12 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs nav-tabs-line" id="examTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#yearly-results" role="tab">
                                    {{ __('Yearly Results') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="attendance-tab" data-toggle="tab" href="#subject-wise-results" role="tab">
                                    {{ __('Subject Wise Results') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="exam-tab" data-toggle="tab" href="#rank-wise-results" role="tab">
                                    {{ __('Rank Wise Results') }}
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content border-0 px-0" id="examTabContent">
                            <div class="tab-pane fade show active py-3" id="yearly-results" role="tabpanel">
                                @include('reports.exam.yearly-results-tab')
                            </div>
                            <!-- Other tab panes can be filled as needed -->
                            <div class="tab-pane fade py-3" id="subject-wise-results" role="tabpanel">
                                @include('reports.exam.subject-wise-results-tab')
                            </div>
                            <div class="tab-pane fade py-3" id="rank-wise-results" role="tabpanel">
                                @include('reports.exam.rank-wise-results-tab')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
