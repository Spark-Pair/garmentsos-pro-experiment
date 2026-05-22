<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $sales_returns = SalesReturn::with('article', 'invoice.customer.city')->orderBy('id', 'desc')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $sales_returns = SalesReturn::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $sales_returns, 'authLayout' => $authLayout]);
        }

        return view('sales-return.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $customers = Customer::whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->with('city')->get()->makeHidden('creator');

        $customerOptions = $customers->mapWithKeys(function ($customer) {
            return [$customer->id => ['text' => $customer->customer_name . ' | ' . $customer->city->short_title]];
        })->toArray();

        return view('sales-return.return', compact('customerOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
            'article_id' => 'required|integer|exists:articles,id',
            'invoice_id' => 'required|integer|exists:invoices,id',
            'date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'amount' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        SalesReturn::create([
            'article_id' => $data['article_id'],
            'invoice_id' => $data['invoice_id'],
            'date' => $data['date'],
            'quantity' => $data['quantity'],
            'amount' => $data['amount'],
        ]);

        CustomerPayment::create([
            'customer_id' => $data['customer_id'],
            'date' => $data['date'],
            'type' => 'sales_return',
            'method' => 'return',
            'amount' => $data['amount'],
        ]);

        return redirect()->back()->with('success', 'Sales return successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesReturn $salesReturn)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesReturn $salesReturn)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalesReturn $salesReturn)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesReturn $salesReturn)
    {
        //
    }

    public function getDetails(Request $request)
    {
        if ($request->customer_id && $request->getArticles) {
            $customer = Customer::find($request->customer_id);

            if (!$customer) {
                return collect();
            }

            return $customer->invoices()
                ->with('invoiceArticles.article')
                ->get()
                ->flatMap(fn($invoice) => $invoice->invoiceArticles)
                ->filter(fn($invoiceArticle) => (int) ($invoiceArticle->invoice_pcs ?? 0) > 0 && $invoiceArticle->article)
                ->groupBy('article_id')
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'id' => $first->article_id,
                        'article_no' => $first->article?->article_no ?? '-',
                    ];
                })
                ->sortBy('article_no')
                ->values();
        }

        if ($request->customer_id && $request->article_id && $request->getInvoices) {
            $customer = Customer::find($request->customer_id);

            if (!$customer) {
                return collect();
            }

            $invoices = $customer->invoices()
                ->with(['order', 'shipment', 'invoiceArticles'])
                ->get();

            $articleId = (int) $request->article_id;
            $article = Article::find($articleId);

            if (!$article) {
                return collect();
            }

            $salesRate = $article->sales_rate;

            return $invoices
                ->filter(function ($invoice) use ($articleId) {
                    return $invoice->invoiceArticles
                        ->pluck('article_id')
                        ->contains($articleId);
                })
                ->map(function ($invoice) use ($articleId, $salesRate) {
                    $articles_in_invoice = $invoice->invoiceArticles
                        ->filter(fn($article) => (int) $article->article_id === $articleId)
                        ->values();

                    $articles = $articles_in_invoice->map(fn($article_in_invoice) => [
                        'id' => (int) $article_in_invoice->article_id,
                        'invoice_quantity' => (int) ($article_in_invoice->invoice_pcs ?? 0),
                        'sales_rate' => $salesRate,
                    ])->all();

                    return [
                        'id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no,
                        'date' => $invoice->date,
                        'articles_in_invoice' => $articles,
                        'discount' => optional($invoice->order)->discount
                                    ?? optional($invoice->shipment)->discount,
                        'sales_rate' => $salesRate,
                    ];
                });
        }

        return collect();
    }
}
