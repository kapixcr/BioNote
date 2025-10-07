<?php

namespace App\Console\Commands;

use App\Models\Veterinaria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class VeterinariaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'veterinaria:manage 
                            {action : Acción a realizar (list, create, delete, show)}
                            {--id= : ID de la veterinaria (para show y delete)}
                            {--pais= : Filtrar por país (para list)}
                            {--ciudad= : Filtrar por ciudad (para list)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestionar veterinarias desde la línea de comandos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'list':
                $this->listVeterinarias();
                break;
            case 'create':
                $this->createVeterinaria();
                break;
            case 'show':
                $this->showVeterinaria();
                break;
            case 'delete':
                $this->deleteVeterinaria();
                break;
            default:
                $this->error('Acción no válida. Use: list, create, show, delete');
                return 1;
        }

        return 0;
    }

    private function listVeterinarias()
    {
        $query = Veterinaria::query();

        if ($pais = $this->option('pais')) {
            $query->porPais($pais);
        }

        if ($ciudad = $this->option('ciudad')) {
            $query->porCiudad($ciudad);
        }

        $veterinarias = $query->get();

        if ($veterinarias->isEmpty()) {
            $this->info('No se encontraron veterinarias.');
            return;
        }

        $headers = ['ID', 'Veterinaria', 'Responsable', 'Ciudad', 'País', 'Email'];
        $rows = $veterinarias->map(function ($vet) {
            return [
                $vet->id,
                $vet->veterinaria,
                $vet->responsable,
                $vet->ciudad,
                $vet->pais,
                $vet->email,
            ];
        });

        $this->table($headers, $rows);
        $this->info("Total: {$veterinarias->count()} veterinarias");
    }

    private function createVeterinaria()
    {
        $this->info('Crear nueva veterinaria');
        $this->line('');

        $data = [
            'veterinaria' => $this->ask('Nombre de la veterinaria'),
            'responsable' => $this->ask('Responsable'),
            'direccion' => $this->ask('Dirección'),
            'telefono' => $this->ask('Teléfono'),
            'email' => $this->ask('Email'),
            'registro_oficial_veterinario' => $this->ask('Registro oficial veterinario'),
            'ciudad' => $this->ask('Ciudad'),
            'provincia_departamento' => $this->ask('Provincia/Departamento'),
            'usuario' => $this->ask('Usuario'),
        ];

        // Seleccionar país
        $paises = Veterinaria::getPaisesValidos();
        $data['pais'] = $this->choice('País', $paises);

        // Solicitar contraseña
        $password = $this->secret('Contraseña');
        $data['password'] = Hash::make($password);

        // Aceptación de términos
        $data['acepta_terminos'] = $this->confirm('¿Acepta los términos y condiciones?', true);
        $data['acepta_tratamiento_datos'] = $this->confirm('¿Acepta el tratamiento de datos?', true);

        // Validar datos
        $validator = Validator::make($data, [
            'veterinaria' => 'required|string|max:255',
            'responsable' => 'required|string|max:255',
            'email' => 'required|email|unique:veterinarias,email',
            'usuario' => 'required|string|unique:veterinarias,usuario',
            'acepta_terminos' => 'accepted',
            'acepta_tratamiento_datos' => 'accepted',
        ]);

        if ($validator->fails()) {
            $this->error('Errores de validación:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("- {$error}");
            }
            return;
        }

        try {
            $veterinaria = Veterinaria::create($data);
            $this->info("Veterinaria creada exitosamente con ID: {$veterinaria->id}");
        } catch (\Exception $e) {
            $this->error("Error al crear la veterinaria: {$e->getMessage()}");
        }
    }

    private function showVeterinaria()
    {
        $id = $this->option('id') ?? $this->ask('ID de la veterinaria');

        $veterinaria = Veterinaria::find($id);

        if (!$veterinaria) {
            $this->error("No se encontró la veterinaria con ID: {$id}");
            return;
        }

        $this->info("Información de la veterinaria:");
        $this->line('');
        $this->line("ID: {$veterinaria->id}");
        $this->line("Veterinaria: {$veterinaria->veterinaria}");
        $this->line("Responsable: {$veterinaria->responsable}");
        $this->line("Dirección: {$veterinaria->direccion}");
        $this->line("Teléfono: {$veterinaria->telefono}");
        $this->line("Email: {$veterinaria->email}");
        $this->line("Registro: {$veterinaria->registro_oficial_veterinario}");
        $this->line("Ciudad: {$veterinaria->ciudad}");
        $this->line("Provincia/Departamento: {$veterinaria->provincia_departamento}");
        $this->line("País: {$veterinaria->pais}");
        $this->line("Usuario: {$veterinaria->usuario}");
        $this->line("Creado: {$veterinaria->created_at}");
        $this->line("Actualizado: {$veterinaria->updated_at}");
    }

    private function deleteVeterinaria()
    {
        $id = $this->option('id') ?? $this->ask('ID de la veterinaria a eliminar');

        $veterinaria = Veterinaria::find($id);

        if (!$veterinaria) {
            $this->error("No se encontró la veterinaria con ID: {$id}");
            return;
        }

        $this->info("Veterinaria a eliminar:");
        $this->line("- {$veterinaria->veterinaria} ({$veterinaria->email})");

        if (!$this->confirm('¿Está seguro de que desea eliminar esta veterinaria?')) {
            $this->info('Operación cancelada.');
            return;
        }

        try {
            $veterinaria->delete();
            $this->info('Veterinaria eliminada exitosamente.');
        } catch (\Exception $e) {
            $this->error("Error al eliminar la veterinaria: {$e->getMessage()}");
        }
    }
}