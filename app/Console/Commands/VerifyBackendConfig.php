<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyBackendConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backend:verify 
                            {--user-id= : ID del usuario a verificar}
                            {--email= : Email del usuario a verificar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar la configuración del backend para autenticación y permisos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Verificación de Configuración del Backend ===');
        $this->line('');

        // Verificar estructura de la base de datos
        $this->verifyDatabaseStructure();

        // Verificar configuración de autenticación
        $this->verifyAuthConfig();

        // Verificar usuarios admin
        $this->verifyAdminUsers();

        // Verificar usuario específico si se proporciona
        if ($this->option('user-id') || $this->option('email')) {
            $this->verifySpecificUser();
        }

        $this->line('');
        $this->info('=== Verificación completada ===');
        
        return 0;
    }

    private function verifyDatabaseStructure()
    {
        $this->info('1. Verificando estructura de la base de datos...');
        
        // Verificar tabla users
        if (!Schema::hasTable('users')) {
            $this->error('   ❌ La tabla "users" no existe');
            return;
        }
        $this->line('   ✓ Tabla "users" existe');

        // Verificar columna role
        if (!Schema::hasColumn('users', 'role')) {
            $this->error('   ❌ La columna "role" no existe en la tabla "users"');
            $this->warn('   Ejecuta: php artisan migrate');
            return;
        }
        $this->line('   ✓ Columna "role" existe en la tabla "users"');

        // Verificar tabla personal_access_tokens
        if (!Schema::hasTable('personal_access_tokens')) {
            $this->error('   ❌ La tabla "personal_access_tokens" no existe (requerida por Sanctum)');
            $this->warn('   Ejecuta: php artisan migrate');
            return;
        }
        $this->line('   ✓ Tabla "personal_access_tokens" existe');
        
        $this->line('');
    }

    private function verifyAuthConfig()
    {
        $this->info('2. Verificando configuración de autenticación...');
        
        $guards = config('auth.guards');
        
        // Verificar guard admin
        if (!isset($guards['admin'])) {
            $this->error('   ❌ El guard "admin" no está configurado');
            return;
        }
        $this->line('   ✓ Guard "admin" configurado');

        if ($guards['admin']['driver'] !== 'sanctum') {
            $this->warn('   ⚠ El guard "admin" no usa Sanctum como driver');
        } else {
            $this->line('   ✓ Guard "admin" usa Sanctum');
        }

        if ($guards['admin']['provider'] !== 'users') {
            $this->warn('   ⚠ El guard "admin" no usa el provider "users"');
        } else {
            $this->line('   ✓ Guard "admin" usa el provider "users"');
        }

        // Verificar provider users
        $providers = config('auth.providers');
        if (!isset($providers['users']) || $providers['users']['model'] !== \App\Models\User::class) {
            $this->warn('   ⚠ El provider "users" no está configurado correctamente');
        } else {
            $this->line('   ✓ Provider "users" configurado correctamente');
        }
        
        $this->line('');
    }

    private function verifyAdminUsers()
    {
        $this->info('3. Verificando usuarios administradores...');
        
        $adminUsers = User::where('role', 'admin')->get();
        
        if ($adminUsers->isEmpty()) {
            $this->warn('   ⚠ No se encontraron usuarios con role="admin"');
            $this->line('   Para crear un admin, ejecuta:');
            $this->line('   php artisan tinker');
            $this->line('   >>> $user = User::find(1);');
            $this->line('   >>> $user->role = "admin";');
            $this->line('   >>> $user->save();');
        } else {
            $this->line("   ✓ Se encontraron {$adminUsers->count()} usuario(s) administrador(es):");
            
            $headers = ['ID', 'Nombre', 'Email', 'Role', 'Tokens Activos'];
            $rows = $adminUsers->map(function ($user) {
                $tokenCount = $user->tokens()->count();
                return [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->role ?? 'no definido',
                    $tokenCount,
                ];
            });
            
            $this->table($headers, $rows);
        }
        
        // Verificar usuarios con roles inválidos
        $invalidRoleUsers = User::whereNotIn('role', ['user', 'admin'])
            ->orWhereNull('role')
            ->get();
            
        if ($invalidRoleUsers->isNotEmpty()) {
            $this->warn("   ⚠ Se encontraron {$invalidRoleUsers->count()} usuario(s) con roles inválidos o nulos:");
            foreach ($invalidRoleUsers as $user) {
                $this->line("      - ID: {$user->id}, Email: {$user->email}, Role: " . ($user->role ?? 'NULL'));
            }
        }
        
        $this->line('');
    }

    private function verifySpecificUser()
    {
        $this->info('4. Verificando usuario específico...');
        
        $user = null;
        
        if ($userId = $this->option('user-id')) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("   ❌ No se encontró usuario con ID: {$userId}");
                return;
            }
        } elseif ($email = $this->option('email')) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("   ❌ No se encontró usuario con email: {$email}");
                return;
            }
        }
        
        if ($user) {
            $this->line("   Usuario encontrado:");
            $this->line("      ID: {$user->id}");
            $this->line("      Nombre: {$user->name}");
            $this->line("      Email: {$user->email}");
            $this->line("      Role: " . ($user->role ?? 'NULL'));
            $this->line("      Es Admin: " . ($user->isAdmin() ? 'Sí' : 'No'));
            
            // Verificar tokens
            $tokens = $user->tokens()->get();
            $this->line("      Tokens activos: {$tokens->count()}");
            
            if ($tokens->isNotEmpty()) {
                $this->line("      Detalles de tokens:");
                foreach ($tokens as $token) {
                    $abilities = implode(', ', $token->abilities ?? []);
                    $this->line("         - Token ID: {$token->id}, Habilidades: [{$abilities}], Creado: {$token->created_at}");
                }
            }
            
            // Verificar role en la base de datos directamente
            $dbRole = DB::table('users')->where('id', $user->id)->value('role');
            if ($dbRole !== $user->role) {
                $this->error("      ⚠ INCONSISTENCIA: Role en modelo: {$user->role}, Role en BD: {$dbRole}");
            } else {
                $this->line("      ✓ Role consistente en modelo y base de datos");
            }
        }
        
        $this->line('');
    }
}

