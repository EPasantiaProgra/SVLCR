<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashoutController extends Component
{
    public $fromDate, $toDate, $userid, $total, $items, $sales, $details, $ventaT;
    public function mount()
    {
        $this->ventaT=sale::sum('total');
        
        $this->fromDate = null;
        $this->toDate = null;
        $this->userid = 0;
        $this ->total = sale::sum('total');
        
        $this->sales = [];
        $this->details = [];
    } 
    public function render()
    {
        return view('livewire.cashout.component',[
            'users' => User::orderBy('name','asc')->get()
        ])
        ->extends('layouts.theme.app')
        ->section('content');
    }

    //metodo consultar
    public function Consultar()
    {
       
        //darle formato a la fecha
        $fi= Carbon::parse($this->fromDate)->format('Y-m-d') . ' 00:00:00';
        $ff= Carbon::parse($this->toDate)->format('Y-m-d') . ' 23:59:59';
        //generar consulta
        $this->sales = Sale::whereBetween('created_at', [$fi, $ff])
        ->where('status', 'Paid')
        ->where('user_id', $this->userid)
        ->get();
        
        $this->total = $this->sales ? $this->sales->sum('total') : 0;
        $this->items = $this->sales ? $this->sales->sum('items') : 0;
        
    }

    //metodo detalle de vista
    public function viewDetails(Sale $sale)
    {
        
        //darle formato a la fecha con carbon
        $fi= Carbon::parse($this->fromDate)->format('Y-m-d') . ' 00:00:00';
        $ff= Carbon::parse($this->toDate)->format('Y-m-d') . ' 23:59:59';
        //consula para los details
        $this->details = Sale::join('sale_details as d','d.sale_id','sales.id')
        ->join('products as p','p.id','d.product_id')
        ->select('d.sale_id','p.name as product', 'd.quantity','d.price')
        ->whereBetween('sales.created_at', [$fi, $ff])
        ->where('sales.status', 'Paid')
        ->where('sales.user_id', $this->userid)
        ->where('sales.id', $sale->id)
        ->get();
        //dd($this->details);

        $this->emit('show-modal' ,'open modal');
    }

    public function Print()
    {
        
    }
}
