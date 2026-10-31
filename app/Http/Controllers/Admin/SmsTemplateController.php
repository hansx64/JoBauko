<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SmsTemplateKey;
use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/sms-templates', [
            'templates' => array_map(function (SmsTemplateKey $key) {
                $template = SmsTemplate::for($key);

                return [
                    'key' => $key->value,
                    'label' => $key->label(),
                    'description' => $key->description(),
                    'placeholders' => $key->placeholders(),
                    'default_body' => $key->defaultBody(),
                    'body' => $template->body,
                    'is_active' => $template->is_active,
                ];
            }, SmsTemplateKey::cases()),
            'maxLength' => SmsTemplate::MAX_LENGTH,
        ]);
    }

    public function update(Request $request, string $key, AuditLogger $audit): RedirectResponse
    {
        $templateKey = SmsTemplateKey::tryFrom($key) ?? abort(404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.SmsTemplate::MAX_LENGTH, function (string $attribute, string $value, \Closure $fail) use ($templateKey) {
                preg_match_all('/\{(\w+)\}/', $value, $matches);
                $unknown = array_diff($matches[1], $templateKey->placeholders());

                if ($unknown !== []) {
                    $fail('Unknown placeholder: {'.implode('}, {', $unknown).'}. Use only the placeholders listed.');
                }
            }],
            'is_active' => ['required', 'boolean'],
        ]);

        $template = SmsTemplate::for($templateKey);
        $template->fill($validated)->save();

        $audit->logChanges('sms_template.updated', $template);

        return back()->with('success', "“{$templateKey->label()}” template saved.");
    }
}
