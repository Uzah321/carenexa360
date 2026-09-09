<?php

namespace App\Modules\CareNotes\Http\Requests;

use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return $this->user()->ownsTenant($serviceUser->tenant_id);
    }

    public function rules(): array
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return [
            // mimetypes (not mimes) — checked against the file's actual
            // content via fileinfo, not its extension, since a browser
            // MediaRecorder upload has no filename/extension at all. The
            // video/* entries aren't a mistake: WebM and Ogg are shared
            // container formats, and fileinfo reports an audio-only
            // MediaRecorder clip in one of those containers as video/webm
            // or video/ogg — there's no audio track marker it can see
            // without fully parsing the container. What's actually stored
            // as this note's mime_type is the browser-reported one, not
            // fileinfo's guess, so playback is unaffected either way.
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/webm,video/webm,audio/ogg,video/ogg,audio/mp4,audio/m4a,audio/mpeg,audio/mp3,audio/wav,audio/x-wav',
                'max:15360',
            ],
            'caption' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists('visits', 'id')
                    ->where('tenant_id', $serviceUser->tenant_id)
                    ->where('service_user_id', $serviceUser->id),
            ],
        ];
    }
}
