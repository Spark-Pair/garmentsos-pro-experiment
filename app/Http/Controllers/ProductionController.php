<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Employee;
use App\Models\Fabric;
use App\Models\Production;
use App\Models\Rate;
use App\Models\ReturnFabric;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'supplier'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        // $productions = Production::with('article', 'work', 'worker')->orderby('id', 'desc')->get();

        if ($request->ajax()) {
            $branches = app(ModuleBranchService::class);
            $productionsQuery = $branches
                ->applyScope(Production::with(['article', 'work', 'worker', 'creator'])->orderByDesc('id'), 'productions');

            if ($this->isSupplierRole()) {
                $supplier = $this->currentSupplier();
                if (!$supplier) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Supplier account not linked with this user.',
                    ], 403);
                }
                $productionsQuery->where('supplier_id', $supplier->id);
            }

            $productions = $productionsQuery->applyFilters($request);

            return response()->json(['data' => $productions, 'authLayout' => $authLayout]);
        }

        return view('productions.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $ticket_options = [];
        $branches = app(ModuleBranchService::class);

        if (Auth::user()->production_type === 'issue') {
            $articles = $branches->applyRelatedScope(Article::whereHas('production.work', function($query) {
                $query->where('title', 'Cutting');
            }), 'articles', 'productions')->with('production.work')->get();
        } else {
            $cmt_work_id = Setup::where('title', 'CMT | E')->value('id') ?? 0;
            $allTickets = $branches->applyScope(Production::whereNull('receive_date'), 'productions')
                ->whereNotNull('ticket')
                ->where('work_id', '!=', $cmt_work_id)
                ->with(['article.production.work', 'work', 'worker'])
                ->orderByDesc('id')
                ->get();
            foreach ($allTickets as $ticket) {
                $ticket_options[$ticket->id] = [
                    'text' => $ticket->ticket,
                    'data_option' => $ticket,
                ];
            }
            $articles = $branches->applyRelatedScope(Article::whereNotNull('fabric_type'), 'articles', 'productions')
                ->whereNotNull('category')
                ->with('production.work')
                ->get();
        }
        $articles->each->setAppends([]);
        $work_options = [];
        $workerTypes = Setup::where('type', 'worker_type')->get();
        foreach($workerTypes as $workerType) {
            $work_options[(int)$workerType->id] = [
                'text' => $workerType->title
            ];
        }
        $worker_options = [];
        $workers = $branches->applyRelatedScope(
                Employee::with('type')->where('category', 'worker')->where('status', 'active'),
                'employees',
                'productions',
            )
            ->get();
        foreach($workers as $worker) {
            $worker['taags'] = $worker['tags']
                ->groupBy('tag')
                ->map(function ($items, $tag) use ($articles) {
                    $fabric = Fabric::where('tag', $tag)->first();
                    $total_return_fabric = ReturnFabric::where('tag', $tag)->sum('quantity');

                    $supplier = null;
                    if ($fabric && $fabric->supplier_id) {
                        $supplier = Supplier::find($fabric->supplier_id);
                    }

                    $sum = $articles->flatMap->production
                        ->flatMap->tags
                        ->filter(fn($tagObj) => $tagObj['tag'] === $tag)
                        ->sum('quantity');

                    $availableQuantity = ($items->sum('quantity') - $sum) - $total_return_fabric;

                    if ($availableQuantity > 0) {
                        return [
                            'tag' => $tag,
                            'unit' => ucfirst($fabric->unit),
                            'available_quantity' => $availableQuantity,
                            'supplier_name' => $supplier->supplier_name ?? null,
                        ];
                    }

                    return null; // keeps mapping consistent
                })
                ->filter() // removes all nulls
                ->values();

            $worker_options[(int)$worker->id] = [
                'text' => $worker->employee_name . ' | ' . Money::format((float) $worker->balance),
                'data_option' => $worker->makeHidden('tags'),
            ];
        }

        $rates = Rate::with('type')->get();

        $branchBranding = app(ModuleBranchService::class)->documentBranding('productions');

        return view('productions.add', compact('articles', 'work_options', 'worker_options', 'rates', 'ticket_options', 'branchBranding'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer|exists:articles,id',
            'work_id' => 'required|integer|exists:setups,id',
            'worker_id' => 'required|integer|exists:employees,id',
            'tags' => 'nullable|string',
            'materials' => 'nullable|string',
            'parts' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'title' => 'nullable|string',
            'rate' => 'nullable|decimal:0,2|min:1',
            'amount' => 'nullable|decimal:0,2|min:1',
            'issue_date' => 'nullable|date',
            'receive_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'article_id' => $request->article_id,
            'work_id' => $request->work_id,
            'worker_id' => $request->worker_id,
            'tags' => isset($request->tags) ? json_decode($request->tags) : null,
            'materials' => isset($request->materials) ? json_decode($request->materials) : null,
            'parts' => isset($request->parts) ? json_decode($request->parts) : null,
            'quantity' => $request->quantity,
            'title' => $request->title,
            'rate' => $request->rate,
            'amount' => $request->amount,
            'issue_date' => $request->issue_date,
            'receive_date' => $request->receive_date,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('productions'),
        ];
        $ticket = null;
        $production = null;

        if ($request->filled('ticket_name') && $request->ticket_name != '-- Select Ticket --') {
            $ticket = $request->ticket_name;
            $production = Production::where('ticket', $request->ticket_name)->first();
            if ($production) {
                $production->update([
                    'receive_date' => $request->receive_date,
                    'tags' => isset($request->tags) ? json_decode($request->tags) : null,
                    'parts' => isset($request->parts) ? json_decode($request->parts) : $production->parts,
                    'title' => $request->title,
                    'rate' => $request->rate,
                    'amount' => $request->amount,
                    'branch_id' => $production->branch_id,
                ]);
            }
        } else {
            if ($request->article_quantity) {
                Article::where('id', $request->article_id)->update(['quantity' => $request->article_quantity]);
            }

            $work = Setup::find($request->work_id);

            $data['ticket'] = 'TEMP';
            $production = Production::create($data);

            $workPrefix = explode('|', $work->short_title)[0];
            $ticket = app(ModuleBranchService::class)->shouldFilterRecords('productions')
                ? app(BranchSerialService::class)->nextProductionTicket($workPrefix)
                : $workPrefix . str_pad($production->id, 3, '0', STR_PAD_LEFT);
            $production->update(['ticket' => $ticket]);
        }

        $issueOrReceive = '';
        if ($request->issue_date) {
            $issueOrReceive = 'issue';
        } else {
            $issueOrReceive = 'receive';
        }

        $production?->loadMissing(['article', 'work', 'worker', 'creator']);

        return redirect()->route('productions.create')
            ->with('success', 'Production ' . $issueOrReceive . ' successfully. Ticket: ' . $ticket)
            ->with('production_ticket_preview', $production ? $this->ticketPreviewPayload($production) : null);
    }

    protected function ticketPreviewPayload(Production $production): array
    {
        return [
            'id' => $production->id,
            'ticket' => $production->ticket,
            'issue_date' => optional($production->issue_date)->format('Y-m-d') ?: (string) $production->issue_date,
            'receive_date' => optional($production->receive_date)->format('Y-m-d') ?: (string) $production->receive_date,
            'article_no' => $production->article?->article_no,
            'article' => $production->article,
            'work' => $production->work,
            'worker' => $production->worker,
            'worker_name' => $production->worker?->employee_name,
            'quantity' => $production->quantity,
            'rate' => $production->rate,
            'amount' => $production->amount,
            'title' => $production->title,
            'parts' => $production->parts,
            'materials' => $production->materials,
            'tags' => $production->tags,
            'creator' => $production->creator?->name,
            'branch_branding' => app(ModuleBranchService::class)->documentBranding('productions', $production),
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(Production $production)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Production $production)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Production $production)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Production $production)
    {
        //
    }
}
