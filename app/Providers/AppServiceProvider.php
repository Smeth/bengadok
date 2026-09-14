<?php

namespace App\Providers;

use App\Console\Commands\AppResetCommand;
use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\Heur;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use App\Models\Zone;
use App\Observers\CommandeObserver;
use App\Observers\CommandeReferentielsCacheObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([AppResetCommand::class]);
        Commande::observe(CommandeObserver::class);

        $referentielsObserver = CommandeReferentielsCacheObserver::class;
        Pharmacie::observe($referentielsObserver);
        Zone::observe($referentielsObserver);
        Livreur::observe($referentielsObserver);
        MontantLivraison::observe($referentielsObserver);
        ModePaiement::observe($referentielsObserver);
        Heur::observe($referentielsObserver);
        TypePharmacie::observe($referentielsObserver);
        AppSetting::observe($referentielsObserver);

        $this->configureDefaults();
        $this->sanitizeViteHotFile();
    }

    /**
     * Évite les pages blanches quand public/hot pointe vers [::1]:5173 (Firefox/Windows).
     */
    private function sanitizeViteHotFile(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        $hotPath = public_path('hot');
        if (! is_file($hotPath)) {
            return;
        }

        $url = trim((string) file_get_contents($hotPath));
        if ($url !== '' && str_contains($url, '[::1]')) {
            @unlink($hotPath);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
