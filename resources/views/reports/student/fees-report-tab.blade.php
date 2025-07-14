<div class="card">
    <div class="card-body">
        <h4 class="card-title">Fees Report</h4>
        @if(isset($studentFees) && count($studentFees) > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('Fees Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Due Date') }}</th>
                        <th>{{ __('Paid Amount') }}</th>
                        <th>{{ __('Payment Mode') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentFees as $fee)
                    <tr>
                        <td>{{ $fee->fees->name ?? '-' }}</td>
                        <td>
                            @if(isset($fee->fees->fees_class_type) && count($fee->fees->fees_class_type) > 0)
                                @if(isset($fee->fees->fees_class_type[0]->fees_type))
                                    {{ $fee->fees->fees_class_type[0]->fees_type->name ?? __('Compulsory') }}
                                @else
                                    {{ __('Compulsory') }}
                                @endif
                            @else
                                {{ __('Compulsory') }}
                            @endif
                        </td>
                        <td>{{ number_format($fee->amount ?? 0, 2) }}</td>
                        <td>{{ $fee->fees->due_date ?? '-' }}</td>
                        <td>
                            @php
                                $paidAmount = 0;
                                if(isset($fee->compulsory_fee) && count($fee->compulsory_fee) > 0) {
                                    foreach($fee->compulsory_fee as $cf) {
                                        $paidAmount += $cf->amount ?? 0;
                                    }
                                }
                            @endphp
                            {{ number_format($paidAmount, 2) }}
                        </td>
                        <td>
                            @if(isset($fee->compulsory_fee) && count($fee->compulsory_fee) > 0)
                                {{ $fee->compulsory_fee[0]->mode ?? '-' }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $fee->date ?? '-' }}</td>
                        <td>
                            @php
                                $status = $fee->status ?? 'unpaid';
                                
                                $badgeClass = 'badge-secondary';
                                if($status == 'paid') {
                                    $badgeClass = 'badge-success';
                                } elseif($status == 'partial') {
                                    $badgeClass = 'badge-warning';
                                } elseif($status == 'unpaid') {
                                    $badgeClass = 'badge-secondary';
                                } elseif($status == 'overdue') {
                                    $badgeClass = 'badge-danger';
                                }
                            @endphp
                            <label class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</label>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="alert alert-info mt-3">
            {{ __('No fees records found for this student.') }}
        </div>
        @endif
    </div>
</div>
