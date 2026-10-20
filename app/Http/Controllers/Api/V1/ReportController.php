<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Http\Resources\CashSessionResource;
use App\Services\ReportService;
use App\Support\ApiResponse;
use App\Support\CsvStreamer;
use App\Support\NativePdfWriter;
use App\Support\NativeXlsxWriter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function dashboard(ReportFilterRequest $request): JsonResponse
    {
        $data = $this->reports->dashboard($request->user(), $request->validated());
        if (($data['cash'] ?? null) !== null && ! is_array($data['cash'])) {
            $data['cash'] = (new CashSessionResource($data['cash']->loadMissing('user')))->resolve();
        }

        return ApiResponse::success($data, 'Dashboard');
    }

    public function salesByDay(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success(
            $this->reports->salesByDay($this->reports->filters($request->validated(), $request->user())),
            'Ventas por día'
        );
    }

    public function topProducts(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success(
            $this->reports->topProducts($this->reports->filters($request->validated(), $request->user())),
            'Top productos'
        );
    }

    public function byCategory(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success(
            $this->reports->byCategory($this->reports->filters($request->validated(), $request->user())),
            'Ventas por categoría'
        );
    }

    public function byCashier(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success(
            $this->reports->byCashier($this->reports->filters($request->validated(), $request->user())),
            'Ventas por cajero'
        );
    }

    public function profit(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success(
            $this->reports->profit($this->reports->filters($request->validated(), $request->user())),
            'Utilidad'
        );
    }

    public function slowMovers(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success(
            $this->reports->slowMovers($this->reports->filters($request->validated(), $request->user())),
            'Baja rotación'
        );
    }

    public function inventoryValue(ReportFilterRequest $request): JsonResponse
    {
        $this->requireAll($request);

        return ApiResponse::success($this->reports->inventoryValue(), 'Valorización de inventario');
    }

    public function export(ReportFilterRequest $request): Response
    {
        $this->requireAll($request);
        $filters = $this->reports->filters($request->validated(), $request->user());
        $report = $request->validated('report') ?? 'top-products';
        $format = $request->validated('format') ?? 'csv';
        $table = $this->reports->table($report, $filters);
        $filename = $report.'-'.now()->toDateString();

        if ($format === 'xlsx') {
            return response(NativeXlsxWriter::bytes($table['headers'], $table['rows']), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
            ]);
        }

        if ($format === 'pdf') {
            return response(NativePdfWriter::bytes($table['title'], $table['headers'], $table['rows']), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return CsvStreamer::download($filename.'.csv', $table['headers'], $table['rows']);
    }

    private function requireAll(ReportFilterRequest $request): void
    {
        abort_unless($this->reports->seesAll($request->user()), 403);
    }
}
