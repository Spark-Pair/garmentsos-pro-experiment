<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentArticles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $shipments = Shipment::with( 'articles.article')->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $shipments, 'authLayout' => $authLayout]);
        }

        // $shipments = Shipment::with('articles.article')->get();

        // if ($shipments->isNotEmpty()) {
        //     foreach ($shipments as $shipment) {
        //         // Invoice exist check
        //         $shipment->isInvoiceHas = Invoice::where('shipment_no', $shipment->shipment_no)->exists();
        //     }
        // }

        return view('shipments.index', compact( 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $customers_options = [];
        $articles = [];

        if ($request->date) {
            $customers = Customer::with('city')
                ->whereHas('user', fn($query) => $query->where('status', 'active'))
                ->where('date', '<=', $request->date)
                ->select('id', 'customer_name', 'person_name', 'urdu_title', 'phone_number', 'date', 'city_id', 'address')
                ->get();

            foreach ($customers as $customer) {
                $customers_options[(int)$customer->id] = [
                    'text' => $customer->customer_name . ' | ' . $customer->city->title,
                    'data_option' => [
                        'id' => $customer->id,
                        'customer_name' => $customer->customer_name,
                        'person_name' => $customer->person_name,
                        'urdu_title' => $customer->urdu_title,
                        'phone_number' => $customer->phone_number,
                        'address' => $customer->address,
                        'date' => $customer->date?->format('Y-m-d'),
                        'city' => [
                            'id' => $customer->city?->id,
                            'title' => $customer->city?->title,
                            'short_title' => $customer->city?->short_title,
                        ],
                    ],
                ];
            }

            $articles = Article::where('date', '<=', $request->date)
                ->where('sales_rate', '>', 0)
                ->whereNotNull(['category', 'fabric_type'])
                ->orderByDesc('id')
                ->get();

            $stockMap = $this->articleStockMap($articles->pluck('id'));

            foreach ($articles as $article) {
                $stock = $stockMap->get($article->id, []);
                $article['physical_quantity'] = (int) ($stock['current_stock_pcs'] ?? 0);
                $article['available_stock'] = (int) ($stock['available_stock_pcs'] ?? 0);
                $article['ordered_quantity'] = (int) ($stock['open_order_pcs'] ?? 0);
            }
        }

        $last_shipment = Shipment::orderby('id', 'desc')->first();

        if (!$last_shipment) {
            $last_shipment = new Shipment();
            $last_shipment->shipment_no = '0000';
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'articles' => $articles
            ]);
        }

        return view('shipments.generate', compact('customers_options', 'last_shipment'));
        // return $articles;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // return $request->all();

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'discount' => 'required|integer',
            'netAmount' => 'required|string',
            'articles' => 'required|json',
            'city' => 'required|string',
            'shipment_no' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $articles = json_decode($request->articles, true) ?? [];
            $this->validateShipmentArticleStock($articles);

            $shipment = Shipment::create([
                'date' => $request->date,
                'discount' => $request->discount,
                'netAmount' => str_replace(',', '', $request->netAmount),
                'articles' => $request->articles,
                'city' => $request->city,
                'shipment_no' => $request->shipment_no,
            ]);

            foreach ($articles as $article) {
                ShipmentArticles::create([
                    'shipment_id' => $shipment['id'],
                    'article_id' => $article['id'],
                    'description' => $article['description'] ?? null,
                    'shipment_pcs' => $article['shipment_quantity'] ?? 0,
                ]);
            }
        });

        return redirect()->route('shipments.create')->with('success', 'Shipment generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Shipment $shipment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Shipment $shipment)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $shipment->load('articles.article');

        $shipmentPayload = [
            'date' => $shipment->date?->format('Y-m-d'),
            'articles' => $shipment->articles->map(function ($shipmentArticle) {
                return [
                    'shipment_pcs' => $shipmentArticle->shipment_pcs,
                    'description' => $shipmentArticle->description,
                    'article' => [
                        'id' => $shipmentArticle->article?->id,
                        'article_no' => $shipmentArticle->article?->article_no,
                        'image' => $shipmentArticle->article?->image,
                        'category' => $shipmentArticle->article?->category,
                        'season' => $shipmentArticle->article?->season,
                        'size' => $shipmentArticle->article?->size,
                    ],
                ];
            })->toArray(),
        ];

        return view('shipments.edit', compact('shipment', 'shipmentPayload'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shipment $shipment)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'netAmount' => 'required|string',
            'articles' => 'required|json',
            'city' => 'required|string',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request, $shipment) {
            $articles = json_decode($request->articles, true) ?? [];
            $existingShipmentPcs = $shipment->articles()
                ->get(['article_id', 'shipment_pcs'])
                ->groupBy('article_id')
                ->map(fn ($lines) => (int) $lines->sum('shipment_pcs'));

            $this->validateShipmentArticleStock($articles, $existingShipmentPcs);

            ShipmentArticles::where('shipment_id', $shipment->id)->delete();

            $shipment->update([
                'netAmount' => str_replace(',', '', $request->netAmount),
                'articles' => $request->articles,
                'city' => $request->city,
            ]);

            foreach ($articles as $article) {
                ShipmentArticles::create([
                    'shipment_id' => $shipment['id'],
                    'article_id' => $article['id'],
                    'description' => $article['description'] ?? null,
                    'shipment_pcs' => $article['shipment_quantity'] ?? 0,
                ]);
            }
        });

        return redirect()->route('shipments.index')->with('success', 'Shipment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shipment $shipment)
    {
        //
    }

    private function validateShipmentArticleStock(array $articles, $existingShipmentPcs = null): void
    {
        $lines = collect($articles)
            ->filter(fn ($line) => is_array($line))
            ->map(function ($line) {
                return [
                    'article_id' => (int) ($line['id'] ?? 0),
                    'shipment_pcs' => (int) ($line['shipment_quantity'] ?? 0),
                ];
            })
            ->filter(fn ($line) => $line['article_id'] > 0 && $line['shipment_pcs'] > 0)
            ->groupBy('article_id')
            ->map(fn ($group) => (int) $group->sum('shipment_pcs'));

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'articles' => 'Please select at least one article.',
            ]);
        }

        $existingShipmentPcs = collect($existingShipmentPcs ?? []);
        $stockMap = $this->articleStockMap($lines->keys());
        $articlesById = Article::query()
            ->whereIn('id', $lines->keys())
            ->get(['id', 'article_no'])
            ->keyBy('id');

        foreach ($lines as $articleId => $shipmentPcs) {
            $availablePcs = (int) ($stockMap->get((int) $articleId)['available_stock_pcs'] ?? 0);
            $maxShipmentPcs = $availablePcs + (int) ($existingShipmentPcs->get((int) $articleId) ?? 0);

            if ($shipmentPcs > $maxShipmentPcs) {
                $articleNo = $articlesById->get((int) $articleId)?->article_no ?? $articleId;
                throw ValidationException::withMessages([
                    'articles' => "Stock is less than shipment quantity for article: {$articleNo}. Available: {$maxShipmentPcs} pcs.",
                ]);
            }
        }
    }
}
