<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use App\Product;
use File;
use Image;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->orderBy('created_at', 'DESC')->paginate(10);
        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name', 'ASC')->get();
        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        //validasi data
        $this->validate($request, [
            'code' => 'required|string|max:10|unique:products',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:100',
            'stock' => 'required|integer',
            'price' => 'required|integer',
            'category_id' => 'required|exists:categories,id',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg'
        ]);

        try {
            //default photonya null
            $photo = null;

            if ($request->hasFile('photo')) {
                $photo = $this->saveFile($request->name, $request->file('photo'));
            }

            //simpan data
            $product = Product::create([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'stock' => $request->stock,
                'price' => $request->price,
                'category_id' => $request->category_id,
                'photo' => $photo
            ]);

            return redirect(route('produk.index'))
                ->with(['success' => '<b>' .$product->name . '</b> Ditambahkan']);

        }catch(\Exception $e) {
            //jika gagal
            return redirect()->back()->with(['error' => $e->getMessage()]);
        }
    }

    private function saveFile($name, $photo)
    {
        //set nama file
        $images = str_slug($name) . time() . '.' . $photo->getClientOriginalExtension();
        //set path
        $path = public_path('uploads/product');

        if (!File::isDirectory($path)) {
            //buat folder
            File::makeDirectory($path, 0777, true, true);
        }

        //simpan gambar
        Image::make($photo)->save($path . '/' . $images);

        //return nama file
        return $images;
    }

    public function destroy($id)
    {
        //query select berdasarkan id
        $products = Product::findOrFail($id);

        if (!empty($product->photo)) {
            File::delete(public_path('uploads/product' . $products->photo));
        }

        $products->delete();
        return redirect()->back()
            ->with(['success' => '<b>' . $products->name . '</b> Telah Dihapus.']);
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $categories = Category::orderBy('name', 'ASC')->get();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, $id)
    {
        //validasi
        $this->validate($request, [
            'code' => 'required|string|max:10|exists:products,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:100',
            'stock' => 'required|integer',
            'price' => 'required|integer',
            'category_id' => 'required|exists:categories,id',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg'
        ]);

        try {
            //select berdasarkan id
            $product = Product::findOrFail($id);
            $photo = $product->photo;

            //cek jika da file
            if ($request->hasFile('photo')) {
                //cek, apabila ada photo, maka photo yang ada di folder uploads/product akan dihapus
                !empty($photo) ? File::delete(public_path('uploads/product/' . $photo)) : null;
                //upload
                $photo = $this->saveFile($request->name, $request->file('photo'));

            }

            //perbaharui
            $product->update([
                'name' => $request->name,
                'description' => $request->description,
                'stock' => $request->stock,
                'price' => $request->price,
                'category_id' => $request->category_id,
                'photo' => $photo
            ]);

            return redirect(route('produk.index'))
                ->with(['success' => '<b>' . $product->name . '</b> Diperbaharui']);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with(['error' => $e->getMessage()]);
        }
    }
}
