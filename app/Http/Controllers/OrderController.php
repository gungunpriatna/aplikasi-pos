<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;
use App\Order;
use App\Order_detail;
use App\Customer;
use App\User;
use Carbon\Carbon;
use Cookie;
use DB;
use PDF;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        //ambil data customer
        $customers = Customer::orderBy('name', 'ASC')->get();
        //ambil data user yang memiliki role kasir
        $users = User::role('kasir')->orderBy('name', 'ASC')->get();
        //ambil data transaksi
        $orders = Order::orderBy('created_at', 'DESC')->with('order_detail', 'customer');

        //jika pelanggan dipilih pada combobox
        if (!empty($request->customer_id)) {
            $orders = $orders->where('customer_id', $request->customer_id);
        }

        //jika user / kasir dipilih pada combobox
        if (!empty($request->user_id)) {
            //maka tambahkan where condition
            $orders = $orders->where('user_id', $request->user_id);

        }

        //jika start date dan end date terisi
        if (!empty($request->start_date) && !empty($request->end_date)) {
            //maka validasi di mana formatnya harus date
            $this->validate($request, [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $start_date = Carbon::parse($request->start_date)->format('Y-m-ad') . ' 00:00:01';
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d') . ' 23:59:59';

            //ditambahkan di wherebetwwen condition untuk ambil data
            $orders = $orders->whereBetween('created_at', [$start_date, $end_date])->get();
        } else {
            //jika start date dan end date kosong, maka load 10 data terbaru
            $orders = $orders->take(10)->skip(0)->get();
        }

        //menampilkan ke view
        return view('orders.index', [
            'orders' => $orders,
            'sold' => $this->countItem($orders),
            'total' => $this->countTotal($orders),
            'total_customer' => $this->countCustomer($orders),
            'customers' => $customers,
            'users' => $users
        ]);
    }

    

    public function addOrder()
    {
        $products = Product::orderBy('created_at', 'DESC')->get();
        return view('orders.add', compact('products'));
    }

    public function getProduct($id)
    {
        $products = Product::findOrFail($id);
        return response()->json($products, 200);
    }

    public function addToCart(Request $request)
    {
        //validasi
        $this->validate($request, [
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer'
        ]);

        //ambil data product berdasarkan id
        $product = Product::findOrFail($request->product_id);

        //ambil cookie cart dengan $request->cookie('cart')
        $getCart = json_decode($request->cookie('cart'), true);

        //jika datanya ada
        if ($getCart) {
            //jika keynya ada berdasarkan product_id
            if (array_key_exists($request->product_id, $getCart)) {
                //jumlahkan qty barang
                $getCart[$request->product_id]['qty'] += $request->qty;
                //kirim untuk disimpan ke cookie
                return response()->json($getCart, 200)
                    ->cookie('cart', json_encode($getCart), 120);
            }
        }

        //jika cart kosong, maka tambahkan ke cart baru
        $getCart[$request->product_id] = [
            'code' => $product->code,
            'name' => $product->name,
            'price' => $product->price,
            'qty' => $request->qty
        ];

        //kirim responnya kemudian simpan ke cookie
        return response()->json($getCart, 200)
            ->cookie('cart', json_encode($getCart), 120);
    }

    public function getCart()
    {
        //ambil cart dari cookie
        $cart = json_decode(request()->cookie('cart'), true);
        //kirim kembali dalam bentuk json untuk ditampilkan dengan vuejs
        return response()->json($cart, 200);
    }

    public function removeCart($id)
    {
        $cart = json_decode(request()->cookie('cart'), true);
        //hapus cart
        unset($cart[$id]);
        //cart diperbaharui
        return response()->json($cart, 200)->cookie('cart', json_encode($cart), 120);
    }

    public function checkout()
    {
        return view('orders.checkout');
    }

    public function storeOrder(Request $request)
    {
        //validasi
        $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required|string|max:100',
            'address' => 'required',
            'phone' => 'required|numeric'
        ]);

        //mengambil list cart dari cookie
        $cart = json_decode($request->cookie('cart'), true);
        //manipulasi array untuk menciptakan key baru yakni resul dari hasil perkalian price * qty
        $result = collect($cart)->map(function($value) {
            return [
                'code' => $value['code'],
                'name' => $value['name'],
                'qty' => $value['qty'],
                'price' => $value['price'],
                'result' => $value['price'] * $value['qty']
            ];
        })->all();

        //database transaction
        DB::beginTransaction();
        try {
            //simpan data ke table customers
            $customer = Customer::firstOrCreate([
                'email' => $request->email
            ], [
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone
            ]);

            //simpan data ke table order
            $order = Order::create([
                'invoice' => $this->generateInvoice(),
                'customer_id' => $customer->id,
                'user_id' => auth()->user()->id,
                'total' => array_sum(array_column($result, 'result'))
            ]);

            //looping cart untuk disimpan ke table order_details
            foreach ($result as $key => $row) {
                $order->order_detail()->create([
                    'product_id' => $key,
                    'qty' => $row['qty'],
                    'price' => $row['price']
                ]);
            }

            //apabila tidak terjadi error, penyimpanan diverifikasi
            DB::commit();

            //return status dan message berupa code invoice, dan menghapus cookie
            return response()->json([
                'status' => 'success',
                'message' => $order->invoice,
            ], 200)->cookie(Cookie::forget('cart'));
        } catch(\Exception $e) {
            //jika ada error, maka akan dirollback
            DB::rollback();
            //pesan gagal akan di-return
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function generateInvoice()
    {
        //ambil data dari table orders
        $order = Order::orderBy('created_at', 'DESC');
        //jika sudah terdapat records
        if ($order->count() > 0) {
            //ambil data pertama
            $order = $order->first();
            //explode untuk mendapatkan angkanya
            $explode = explode('-', $order->invoice);
            //angka dari hasil explode +1
            return 'INV-' . $explode[1] + 1;
        }

        //jika belum terdapat records maka akan me-return INV-1
        return 'INV-1';
    }

    private function countCustomer($orders)
    {
        $customer = [];

        if ($orders->count() > 0) {
            //looping untuk menyimpan email ke dalam array
            foreach ($orders as $row) {
                $customer[] = $row->customer->email;
            }
        }

        //menghitung total data yang ada di dalam array
        //di mana data yang duplicate akan dihapus menggunakan array_unique
        return count(array_unique($customer));
    }

    private function countTotal($orders)
    {
        $total = 0;

        if ($orders->count() > 0) {
            //mengambil value dari total, pluck() akan mengubahnya menjadi array
            $sub_total = $orders->pluck('total')->all();
            //kemudian data yang ada di dalam array dijumlahkan
            $total = array_sum($sub_total);
        }

        return $total;
    }

    private function countItem($order)
    {
        $data = 0;

        if ($order->count() > 0) {
            foreach ($order as $row) {
                $qty = $row->order_detail->pluck('qty')->all();

                $val = array_sum($qty);
                $data += $val;
            }
        }

        return $data;
    }

    public function invoicePdf($invoice)
    {
        //ambil data berdasarkan invoice
        $order = Order::where('invoice', $invoice)
            ->with('customer', 'order_detail', 'order_detail.product')->first();
        
        //set config pdf menggunakan font sans-serif
        $pdf = PDF::setOptions(['dpi' => 150, 'defaultFont' => 'sans-serif'])
            ->loadView('orders.report.invoice', compact('order'));
        
        return $pdf->stream();
    }

    public function invoiceExcel($invoice)
    {

    }
}
