@extends('app')
@section('title', 'Physical Quantity Report | ' . $client_company->name)
@section('content')
@php
    $companyData = $client_company;
    $modeLabels = [
        'all_articles' => 'All Articles',
        'article_wise' => 'Article-wise',
        'proceed_by_wise' => 'Proceed By-wise',
    ];
@endphp
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Physical Quantity Report" />
        <x-progress-bar :steps="['Select Options', 'Preview']" :currentStep="1" />
    </div>

    <form id="form" action="#" method="get"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto relative overflow-hidden">
        <x-form-title-bar title="Generate Physical Quantity Report" />

        <div class="step1 space-y-4">
            <x-select
                label="Report Mode"
                name="mode"
                id="mode"
                :options="[
                    'all_articles' => ['text' => 'All Articles'],
                    'article_wise' => ['text' => 'Article-wise'],
                    'proceed_by_wise' => ['text' => 'Proceed By-wise'],
                ]"
                :value="$mode"
                showDefault
                onchange="togglePhysicalQuantityMode()"
            />

            <div id="articleFilterWrap" class="{{ $mode === 'article_wise' ? '' : 'hidden' }}">
                <x-select
                    label="Article"
                    name="article_id"
                    id="article_id"
                    :options="$articleOptions"
                    :value="old('article_id', $data['article_id'] ?? '')"
                    showDefault
                />
            </div>

            <div id="proceedByFilterWrap" class="{{ $mode === 'proceed_by_wise' ? '' : 'hidden' }}">
                <x-input
                    label="Proceed By"
                    name="proceed_by"
                    id="proceed_by"
                    type="text"
                    :value="old('proceed_by', $data['proceed_by'] ?? '')"
                    placeholder="Type proceed by"
                />
            </div>
        </div>

        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2">
            @if (isset($data))
                <div id="preview-container" class="h-full relative">
                    @php
                        $totalPages = $data['pages']->count();
                    @endphp
                    @forelse ($data['pages'] as $page)
                        @php 
                            $serial = 1; // har page pe reset
                        @endphp
                        <div class="preview-page w-[210mm] h-[297mm] mx-auto overflow-hidden relative bg-white p-[0.19in] rounded-md">
                            <div id="preview" class="preview flex flex-col h-full">
                                <div id="preview-document" class="preview-document flex flex-col h-full px-2">
                                    <div id="preview-banner" class="preview-banner w-full flex justify-between items-center pl-5 pr-8">
                                        <div class="flex items-center gap-3">
                                            @if($companyData->logo)
                                                <div class="h-[1.5rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                    <img
                                                        src="{{ asset('images/' . $companyData->logo) }}"
                                                        alt="garmentsos-pro"
                                                        class="max-h-full max-w-full object-contain"
                                                    />
                                                    @if($companyData->logo_text)
                                                        <h1 class="text-md font-bold tracking-wide">
                                                            {{ $companyData->logo_text }}
                                                        </h1>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="right">
                                            <div>
                                                <h1 class="text-xl font-medium text-[var(--primary-color)] pr-2 capitalize">Physical Quantity Report</h1>
                                                <div class="total-bill leading-none mt-1 text-sm">Total Records: {{ $data['rows']->count() }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="w-full my-2 border-gray-700">

                                    <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                                        <div class="preview-table w-full">
                                        <div class="table w-full rounded-lg overflow-hidden text-xs">
                                            <div class="flex gap-[6mm] items-start">
                                                @foreach (['left', 'right'] as $column)
                                                    <div class="w-1/2">
                                                        @if ($page[$column]->isNotEmpty())
                                                            <div class="preview-table w-full">
                                                            <div class="table w-full border border-gray-700 rounded-lg overflow-hidden text-xs">
                                                                <div class="thead w-full">
                                                                    <div class="tr flex w-full px-2 py-0 bg-[var(--primary-color)] text-white text-center">
                                                                        <div class="th font-medium overflow-hidden w-[8%] text-left">#</div>
                                                                        <div class="th font-medium overflow-hidden w-[30%] text-left">Article/pckt.</div>
                                                                        <div class="th font-medium overflow-hidden grow">Proc. By</div>
                                                                        <div class="th font-medium overflow-hidden w-[20%]">Received</div>
                                                                        <div class="th font-medium overflow-hidden w-[20%]">Remainig</div>
                                                                    </div>
                                                                </div>
                                                                <div id="tbody" class="tbody w-full">
                                                                    @foreach ($page[$column] as $row)
                                                                        <div>
                                                                            <hr class="w-full border-dotted border-gray-100">
                                                                            <div class="tr flex  w-full px-2 text-center gap-0.5">
                                                                                <div class="td font-medium overflow-hidden w-[8%] text-left capitalize truncate">{{ $serial++ }}.</div>
                                                                                <div class="td font-medium overflow-hidden w-[30%] text-left truncate">{{ $row['article_no'] }}</div>
                                                                                <div class="td font-medium overflow-hidden grow capitalize truncate">{{ $row['proceed_by'] }}</div>
                                                                                <div class="td font-medium overflow-hidden w-[20%]">{{ $row['received_qty'] }}</div>
                                                                                <div class="td font-medium overflow-hidden w-[20%]">{{ $row['remaining_qty'] }}</div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            </div>
                                                        @else
                                                            <div class="w-full min-h-[30mm] border border-dashed border-gray-300 rounded-lg flex items-center justify-center text-gray-400">
                                                                No data
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        </div>
                                    </div>

                                    <hr class="w-full my-2 border-gray-700">
                                    <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-800 leading-none text-xs" style="font-size: 0.70rem">
                                        <p>Powered by SparkPair &copy; 2025 SparkPair | +92 316 5825495</p>
                                        <p>Page {{ $loop->iteration }} of {{ $totalPages }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="preview-page w-[210mm] h-[297mm] mx-auto bg-white rounded-md overflow-hidden relative p-[0.19in]">
                            <div class="px-[10mm] py-[12mm] text-center text-gray-500">
                                No matching records found for the selected report options.
                            </div>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
    </form>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-physical-quantity.js') }}"></script>
<script>
    window.__reportsPhysicalQuantity = {
        reportUrl: @json(route('reports.physical-quantity')),
    };
</script>
@endpush
