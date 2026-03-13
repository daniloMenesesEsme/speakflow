<?php

namespace App\Http\Controllers\API;

use App\Models\Language;
use Illuminate\Http\JsonResponse;

class LanguageController extends BaseController
{
    public function index(): JsonResponse
    {
        $languages = Language::active()
            ->withCount(['lessons' => fn ($q) => $q->active()])
            ->get()
            ->map(fn ($lang) => [
                'id'           => $lang->id,
                'name'         => $lang->name,
                'code'         => $lang->code,
                'flag_emoji'   => $lang->flag_emoji,
                'lessons_count'=> $lang->lessons_count,
            ]);

        return $this->success($languages, 'Idiomas disponíveis.');
    }

    public function show(Language $language): JsonResponse
    {
        $language->loadCount([
            'lessons'   => fn ($q) => $q->active(),
            'dialogues' => fn ($q) => $q->active(),
        ]);

        $levelBreakdown = $language->lessons()
            ->active()
            ->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level');

        return $this->success([
            'id'              => $language->id,
            'name'            => $language->name,
            'code'            => $language->code,
            'flag_emoji'      => $language->flag_emoji,
            'lessons_count'   => $language->lessons_count,
            'dialogues_count' => $language->dialogues_count,
            'level_breakdown' => $levelBreakdown,
        ]);
    }
}
