<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Order;

class OrderInvoice implements FromView, WithEvents, ShouldAutoSize
{
    use Exportable;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function registerEvents(): array
    {
        //manipulasi cell
        return [
            AfterSheet::class => function(AfterSheet $event) {
                //cell terkait akan di-merge
                $event->sheet->mergeCells('A1:C1');
                $event->sheet->mergeCells('A2:B2');
                $event->sheet->mergeCells('A3:B3');

                //definisikan style untuk cell
                $styleArray = [
                    'font' => [
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                        'rotation' => 90,
                        'startColor' => [
                            'argb' => 'FFA0A0A0',
                        ],
                        'endColor' => [
                            'argb' => 'FFFFFFFF',
                        ],
                    ],
                ];

                //cell terkait akan menggunakan style dari styleArray
                $event->sheet->getStyle('A9:E9')->applyFromArray($styleArray);

                //formating style untuk cell terkait
                $headCustomer = [
                    'font' => [
                        'bold' => true,
                    ],
                ];
                $event->sheet->getStyle('A5:A7')->applyFromArray($headCustomer);
            },
        ];
    }

    public function view(): view
    {
        //ambil data transaksi berdasarkan invoice yang diterima dari controller
        $order = Order::where('invoice', $this->invoice)
            ->with('customer', 'order_detail', 'order_detail.product')->first();

        //data tersebut dipassing ke file invoice_excel
        return view('orders.report.invoice_excel', [
            'order' => $order
        ]);
    }
}