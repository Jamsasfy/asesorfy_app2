<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CustomPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Limpiar la caché de permisos (Buena práctica de Spatie)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Lista de permisos personalizados (Arquitectura Limpia: fácil de leer y mantener)
        $permissions = [
            'asignacion_masiva_asesor_cliente',
            'asignar_asesor_cliente',
            'assign_assessor_proyecto',
            'boton_crear_venta_proyecto',
            'boton_crear_venta_venta',
            'cambiar_asesor_cliente',
            'cambiar_asesor_cliente_cliente',
            'cambiar_estado_cliente',
            'cambiarAsesor_cliente',
            'page_MisClientesAsignados',
            'quitar_asesor_cliente',
            'quitar_asesor_cliente_cliente',
            'reorder_cliente::suscripcion',
            'reorder_factura',
            'reorder_motivo::descarte',
            'reorder_servicio',
            'reorder_tipo::cliente',
            'reorder_variable::configuracion',
            'replicate_cliente::suscripcion',
            'replicate_factura',
            'replicate_motivo::descarte',
            'replicate_servicio',
            'replicate_tipo::cliente',
            'replicate_variable::configuracion',
            'subir_documento_documento',
            'unassign_assessor_proyecto',
            'verificado_documento',
            'verificar_documento',
            'widget_AsesorTotalClientesWidget',
            'widget_ClientesPorMesChart',
        ];

        // 3. Bucle para crear los permisos (Idempotente)
        // Usamos 'web' como guard_name, según tu captura.
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName],
                ['guard_name' => 'web']
            );
        }

        // 4. Opcional: Asignar permisos al Super-Admin
        // $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        // $superAdminRole->givePermissionTo(Permission::all());
    }
}