<?php
declare(strict_types=1);
namespace App\Support;
final class Workspace
{
    public static function links(string $role):array
    {
        return $role==='ADMIN'?['Overview'=>'/admin','Products'=>'/admin/products','Customers'=>'/admin/customers','Inventory'=>'/admin/inventory','Sales Representatives'=>'/admin/representatives','Stock Allocation'=>'/admin/stock/allocate','Stock Returns'=>'/admin/stock/returns','Promotions'=>'/admin/promotions','Invoices'=>'/admin/invoices','Offline Sales Review'=>'/admin/offline','Reports'=>'/admin/reports','Language and company details'=>'/admin/settings/language']:['Overview'=>'/rep','Assigned Stock'=>'/rep/stock','Customers'=>'/rep/customers','Promotions'=>'/rep/promotions','New Invoice'=>'/rep/invoices/new','Sales History'=>'/rep/invoices','Offline Sales'=>'/offline/index.html#new','Sync Status'=>'/offline/index.html#status'];
    }
    public static function icon(string $label):string
    {
        $paths=['Products'=>'M4 7l8-4 8 4v10l-8 4-8-4z M4 7l8 4 8-4 M12 11v10','Customers'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 21v-2a4 4 0 0 0-3-3 M16 3a4 4 0 0 1 0 8','Promotions'=>'M20 13l-7 7-10-10V3h7z M7 7h.01','Invoices'=>'M5 3h14v18l-3-2-4 2-4-2-3 2z M8 7h8 M8 11h8 M8 15h4','Reports'=>'M4 3v18h17 M8 17v-6 M13 17V7 M18 17V4','Offline Sales'=>'M20 7v5h-5 M4 17v-5h5 M6 7a7 7 0 0 1 12-1l2 2 M4 16l2 2a7 7 0 0 0 12-1'];
        $paths+=['Inventory'=>'M3 10l9-7 9 7v11H3z M8 21v-8h8v8 M10 7h4','Sales Representatives'=>'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10 M3 22v-2a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v2','Stock Allocation'=>'M3 8h15 M14 4l4 4-4 4 M21 16H6 M10 12l-4 4 4 4','Stock Returns'=>'M20 7H5 M9 3L5 7l4 4 M4 17h15 M15 13l4 4-4 4'];
        $key=str_contains($label,'Invoice')||$label==='Sales History'?'Invoices':(str_contains($label,'Sync')||$label==='Offline Sales Review'?'Offline Sales':($label==='Assigned Stock'?'Inventory':$label));
        return '<svg class="workspace-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$key]??'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z').'"></path></svg>';
    }
}
