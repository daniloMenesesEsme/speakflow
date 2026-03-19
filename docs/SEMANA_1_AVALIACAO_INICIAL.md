# SpeakFlow — Semana 1 (Avaliacao Inicial / Placement Test)

## Objetivo da Semana 1

Implementar uma avaliacao inicial real no primeiro acesso para medir o nivel do usuario (CEFR) e iniciar uma trilha personalizada de estudos.

---

## Escopo implementado

### 1) Backend de nivelamento (Laravel)

- Criada a tabela `placement_questions` para banco de questoes.
- Criada a tabela `user_placement_results` para historico de resultados.
- Criados os models:
  - `App\Models\PlacementQuestion`
  - `App\Models\UserPlacementResult`
- Adicionado relacionamento no usuario:
  - `User::placementResults()`

### 2) Servico de avaliacao

- Criado `App\Services\PlacementTestService` com responsabilidades:
  - carregar questoes ativas,
  - validar e avaliar respostas,
  - calcular score percentual e score por habilidade,
  - mapear score para nivel CEFR (`A1` a `C2`),
  - atualizar `users.level`,
  - salvar resultado em `user_placement_results`,
  - sugerir licoes iniciais por nivel.

### 3) Endpoints da API

Controller criado: `App\Http\Controllers\API\PlacementTestController`

Rotas (autenticadas em `/api/v1`):

- `GET /placement-test`
  - Retorna questoes de nivelamento.
- `POST /placement-test/submit`
  - Recebe respostas do usuario e retorna resultado.
- `GET /placement-test/latest`
  - Retorna ultimo resultado salvo do usuario.

### 4) Seeder de questoes iniciais

- Criado `Database\Seeders\PlacementQuestionSeeder` com 12 questoes (A1 a B2).
- Registrado no `DatabaseSeeder`.

### 5) Integracao frontend (Next.js)

- Criado `services/placement.service.ts`.
- Atualizada a pagina `app/(onboarding)/onboarding/page.tsx` para:
  - buscar questoes pela API,
  - enviar respostas para avaliacao real,
  - exibir resultado com:
    - nivel CEFR,
    - score geral,
    - breakdown por habilidade (`grammar`, `vocabulary`, `reading`),
    - licoes iniciais recomendadas.

---

## Mapeamento CEFR atual

Regra inicial utilizada em `PlacementTestService::mapScoreToLevel()`:

- `< 25` => `A1`
- `< 45` => `A2`
- `< 65` => `B1`
- `< 80` => `B2`
- `< 92` => `C1`
- `>= 92` => `C2`

> Observacao: esta regra e calibravel em producao com base em dados reais de uso.

---

## Estrutura de payload

### Requisicao de envio

`POST /api/v1/placement-test/submit`

```json
{
  "answers": [
    { "question_id": 1, "answer": "is" },
    { "question_id": 2, "answer": "is" }
  ]
}
```

### Resposta esperada

```json
{
  "success": true,
  "message": "Avaliacao concluida com sucesso.",
  "data": {
    "level": "A2",
    "score_percentage": 41.67,
    "total_questions": 12,
    "correct_answers": 5,
    "skill_breakdown": {
      "grammar": { "total": 6, "correct": 3, "score": 50.0 },
      "vocabulary": { "total": 3, "correct": 1, "score": 33.33 },
      "reading": { "total": 3, "correct": 1, "score": 33.33 }
    },
    "recommended_lessons": []
  }
}
```

---

## Como executar localmente

No backend (`speakflow`):

```bash
php artisan migrate
php artisan db:seed --class=PlacementQuestionSeeder
```

No frontend (`speakflow-web`):

- garantir `NEXT_PUBLIC_API_URL` apontando para o backend correto.
- executar app e abrir fluxo de cadastro/onboarding.

---

## Pendencias da Semana 1 (proximo incremento)

- Aumentar banco de questoes para cobrir `C1` e `C2` com maior confiabilidade.
- Introduzir pesos diferentes por tipo de questao/habilidade.
- Criar `feature flag` para obrigar avaliacao antes do dashboard (quando desejado).
- Expor endpoint de trilha inicial semanal baseada no resultado.

---

## Resultado da entrega

A avaliacao inicial agora existe de forma real e persistida, permitindo classificar o usuario no primeiro uso e direcionar melhor o inicio da jornada de aprendizado no SpeakFlow.

