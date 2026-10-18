<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User\Ability;
use App\Receipts\Ocr\NullOcrEngine;
use App\Receipts\Ocr\OcrEngine;
use App\Receipts\Ocr\TextractOcrEngine;
use App\Receipts\Parsing\ReceiptParser;
use App\Receipts\Parsing\TextReceiptParser;
use Aws\Textract\TextractClient;
use App\Support\Mixins\RequestMixin;
use App\Support\Mixins\UrlMixin;
use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Silber\Bouncer\BouncerFacade as Bouncer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(Hashids::class, fn () => new Hashids(config('app.key'), 10));

        $this->app->bind(ReceiptParser::class, TextReceiptParser::class);
        $this->app->bind(OcrEngine::class, fn () => match (config('receipts.ocr_engine')) {
            'textract' => new TextractOcrEngine(new TextractClient([
                'version' => '2018-06-27',
                'region' => config('receipts.textract.region'),
                // Without a key the SDK falls back to the instance role (EC2/ECS), which is preferred.
                'credentials' => config('receipts.textract.key') ? [
                    'key' => config('receipts.textract.key'),
                    'secret' => config('receipts.textract.secret'),
                ] : null,
            ])),
            default => new NullOcrEngine(),
        });
    }

    public function boot(): void
    {
        Request::mixin(new RequestMixin());
        URL::mixin(new UrlMixin());

        // Use our own role/ability models so custom columns (title, group, excludes) are respected.
        Bouncer::useRoleModel(Role::class);
        Bouncer::useAbilityModel(Ability::class);
    }
}
