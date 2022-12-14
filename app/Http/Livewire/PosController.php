<?php

namespace App\Http\Livewire;

use App\Models\Denomination;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetails;
//
use App\Models\Category;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Livewire\Component;
use DB;
use Illuminate\Support\Facades\Auth;

class PosController extends Component
{
    public $total, $itemsQuantity,  $efectivo, $change, $search;
    private $pagination = 2;
    
    //inicar las propiedades
    public function mount()
    {
        $this->efectivo = 0;
        $this->change = 0;
        $this->total = 0;
        $this->itemsQuantity = 0;
    }
    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function render()
    {   
        //obtener los datos del carrito
        $this->total = Cart::getTotal();
        $this->itemsQuantity = Cart::getTotalQuantity();
        return view('livewire.pos.component',[

            'denominations' => Denomination::orderBy('value','desc')->get(),
            'cart' => Cart::getContent()->sortBy('name')
            ])  
        ->extends('layouts.theme.app')
        ->section('content');
    }

    // metodo para agregar el efectivo tecleado
    public function Acash($value)
    {
        //almacer cada unos de los btn que tenemos en la vista y el btn exacto
        $this->efectivo += ($value == 0) ? $this->total : $value;
        //para el cambio 
        $this->change = ($this->efectivo - $this->total);
        //
    }

    //capturar los eventos 
    protected $listeners =[
        'scan-code' => 'ScanCode',
        'removeItem' => 'removeItem',
        'clearCart' => 'clearCart',
        'saveSale' => 'saveSale',
        'Set' => 'Set'
    ];
    //funcion icone x
    public function Set($efectivo, $change)
    {
        $this->efectivo = $efectivo;
        $this->change = $change;
    }
    //metodo recibir el codigo de barras
    public function ScanCode($barcode, $cant = 1){
        //obtener el producto en base al codigo de barras
        $product = Product::where('barcode', $barcode)->first();
        //verificar si el producto exite
        if($product == null || empty($product))
        {
            dd($product);
            $this->emit('scan-notfound','El producto no esta registrado');
        } else{
            //si existe el producto
            if($this->InCart($product->id))
            {
                $this->increaseQty($product->id);
                return;
            }
            if($product->stock <1)
            {
                $this->emit('No-stock', 'stock insuficiente :C');
                return;
            }
            //metodo del cart descargado
            //agregar al carrito
            Cart::add($product->id, $product->name, $product->price, $cant, $product->image);
            $this->total = Cart::getTotal();
            
            $this->emit('scan-ok','Producto agregado');
        }
    }

    //metodo en carro
    public function InCart($productId)
    {
        $exist = Cart::get($productId);
        //validar si existe algun item
        if($exist)
        {
            return true;
        }
        else{
            return false;
        }
    }

    public function increaseQty($productId, $cant = 1)
    {
        $title='';
        $product = Product::find($productId);
        $exist = Cart::get($productId);
        // validar si existe
        if($exist)
            $title = 'Cantidad Subida';
        else
            $title = 'Cantidad Insuficiente';
        
        if($exist){
            //validar si las existencias de los productos son menores a + de la cantidad 
            if($product->stock < ($cant + $exist->quantity))
            {
                $this->emit('no-stock', 'Stock insuficiente :C');
                return;
            }
        }

        //actualizar el carrito con funcion del cart descargado
        Cart::add($product->id, $product->name, $product->price, $cant, $product->image);
        //actualizar el total
        $this ->total = Cart::getTotal();
        //actualizar items quanyity
        $this->itemsQuantity = Cart::getTotalQuantity();
        //emitir el evento
        $this ->emit('scan-ok', $title);
    }   

    //metodo update quantity donde remplazara toda la info del carrito y la vuelve a poner
    public function updateQty($productId, $cant = 1)
    {
        $title='';
        $product = Product::find($productId);
        //valdiar si existe el producto en el carrito
        $exist = Cart::get($productId);
        if($exist)
        $title = 'Cantidad Actualizada';
        else
        $title = 'Cantidad Actualizada';

        //
        if($exist)
        {
            //si en su columna stock es menor a la cantidad
            if($product->stock < $cant)
            {
                $this->emit('no-stock', 'Stock insuficiente :C');
                return;
            }
        }

        //eliminar el carrito
        $this->removeItem($productId,0,0);
        if($cant >0)
        {
            Cart::add($product->id, $product->name, $product->price, $cant, $product->image);
            //actualizar el total
            $this ->total = Cart::getTotal();
            //actualizar items quanyity
            $this->itemsQuantity = Cart::getTotalQuantity();
            //emitir el evento
            $this ->emit('scan-ok', $title);
        }
    }


    //metodo eliminar un producto de ventas con ayudade las funciones cart
    public function removeItem($productId, $efectivo, $change)
    {
       
        //eliminar
        Cart::remove($productId);

        //actualizar el total
        $this ->total = Cart::getTotal();
        //actualizar items quanyity
        $this->itemsQuantity = Cart::getTotalQuantity();
        //emitir el evento
        $this->efectivo = $efectivo;
        $this->change = $change;
        $this ->emit('scan-ok', 'Producto eliminado');

    }
    //metodo decrementar producto
    public function decreaseQty($productId)
    {
            //recuperar el carrito
        $item = Cart::get($productId);
        //eliminarlo del carrito
        Cart::remove($productId);
        //decrementar la cantidad del producto
        if($item == null)
        {
            return;
        }
        $newQty = ($item->quantity) -1;
        //dd($newQty);
        //validacion
        if($newQty > 0)
        {
            Cart::add($item->id, $item->name, $item->price, $newQty, $item->attributes[0]);
        }
        
        //actualizar el total
        $this->total = Cart::getTotal();
        //actualizar items quanyity
        $this->itemsQuantity = Cart::getTotalQuantity();
        //emitir el evento
        $this ->emit('scan-ok', 'Cantidad bajada');
        
        
        
    }

    //metodo limpiar carrito
    public function clearCart()
    {
        //limpiar
        Cart::clear();
        $this->efectivo = 0;
        $this->change = 0;

         //actualizar el total
         $this ->total = Cart::getTotal();
         //actualizar items quanyity
         $this->itemsQuantity = Cart::getTotalQuantity();
         //emitir el evento
         $this ->emit('scan-ok', 'Carrito Vacio');
    }
    //metodo guardar venta
    public function saveSale()
    {
        //validar el total
        if($this->total <= 0)
        {
            $this->emit('sale-error', 'AGREGA PRODUCTOS A LA VENTA');
            return;
        }

        //validar el efectivo
        if($this->efectivo <= 0)
        {
            $this->emit('sale-error', 'INGRESA EL EFECTIVO');
            return;
        }

        //validar el total
        if($this->total > $this->efectivo)
        {
            $this->emit('sale-error', 'EL EFECTIVO DEBE SER MAYOR O IGUAL A TOTAL');
            return;
        }
        //DB ayuda a si se tuvo una obstruccion en la creacion de la venta se retorne atras
        //para usar las transacciones en laravel
        DB::beginTransaction();

        try {
            //guardar primero la venta
            $sale = Sale::create([
                'total' => $this->total,
                'items' => $this->itemsQuantity,
                'cash' => $this->efectivo,
                'change' => $this->change,
                'user_id' => Auth()->user()->id
            ]);
            //validar si se guardo
            if($sale)
            {
                //guardar detalle de venta
                $items = Cart::getContent();
                foreach ($items as $item) {
                    SaleDetails::create([
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'product_id' => $item->id,
                        'sale_id' => $sale->id
                    ]);
                    //actualizar stock
                    $product = Product::find($item->id);
                    //actualizar el stock
                    $product->stock = $product->stock - $item->quantity;
                    //
                    $product->save();
                }
            }
            //confirma la transaccion
           DB::commit();

           //limpiar el carrito y reinicar las variables

           Cart::clear();
           $this->efectivo = 0;
           $this->change = 0;
            //actualizar el total
            $this ->total = Cart::getTotal();
            //actualizar items quanyity
            $this->itemsQuantity = Cart::getTotalQuantity();
            $this->emit('sale-ok','Venta registrada con exito');
            $this->emit('print-ticket', $sale->id);
        } catch (Exception $e) {
            //borrar las acciones incompletas
            DB::rollback();
            $this->emit('sale-error', $e->getMessage());

        }

    }

    public function printTicket($sale)
    {
        return Redirect::to("print://$sale->id");
    }
}
