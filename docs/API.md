# SpeakFlow — Documentação da API REST

> **Versão:** v1  
> **Base URL (desenvolvimento):** `http://localhost/speakflow/public/api/v1`  
> **Base URL (artisan serve):** `http://127.0.0.1:8000/api/v1`  
> **Autenticação:** JWT Bearer Token (`php-open-source-saver/jwt-auth` v2.8.3)

---

## Sumário

- [Sistema de Autenticação](#sistema-de-autenticação)
- [Como usar o token](#como-usar-o-token)
- [Endpoints de Autenticação](#endpoints-de-autenticação)
- [Idiomas](#idiomas)
- [Lições](#lições)
- [Exercícios](#exercícios)
- [Pronúncia](#pronúncia)
- [Diálogos](#diálogos)
- [Conversações](#conversações)
- [Motor Pedagógico (LearningEngine)](#motor-pedagógico)
- [Conquistas](#conquistas)
- [Sessões de Estudo](#sessões-de-estudo)
- [Formato padrão das respostas](#formato-padrão-das-respostas)
- [Códigos de erro](#códigos-de-erro)

---

## Sistema de Autenticação

| Item | Detalhe |
|------|---------|
| **Pacote** | `php-open-source-saver/jwt-auth` v2.8.3 |
| **Guard** | `api` com driver `jwt` (`config/auth.php`) |
| **Model** | `User` implementa `JWTSubject` |
| **Algoritmo** | HS256 (configurável em `config/jwt.php`) |
| **Expiração** | 60 minutos (padrão) |
| **Renovação** | via `POST /auth/refresh` |

---

## Como usar o token

Após o login, inclua o token em **todas** as requisições autenticadas:

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

---

## Endpoints de Autenticação

### `POST /auth/register` — Criar conta
**Público** · Não requer token

**Body:**
```json
{
  "name":                "João Silva",
  "email":               "joao@email.com",
  "password":            "minhasenha123",
  "password_confirmation":"minhasenha123",
  "native_language":     "pt",
  "target_language":     "en",
  "daily_goal_minutes":  15
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "message": "Conta criada com sucesso!",
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@email.com",
      "native_language": "pt",
      "target_language": "en",
      "level": "A1",
      "daily_goal_minutes": 15,
      "total_xp": 0,
      "streak_days": 0,
      "avatar": null,
      "created_at": "2026-03-11T12:00:00.000000Z"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer"
  }
}
```

---

### `POST /auth/login` — Entrar
**Público** · Não requer token

**Body:**
```json
{
  "email":    "joao@email.com",
  "password": "minhasenha123"
}
```

**Resposta `200 OK`:**
```json
{
  "success": true,
  "message": "Login realizado com sucesso!",
  "data": {
    "user": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@email.com",
      "native_language": "pt",
      "target_language": "en",
      "level": "A1",
      "daily_goal_minutes": 15,
      "total_xp": 120,
      "streak_days": 3,
      "avatar": null,
      "created_at": "2026-03-11T12:00:00.000000Z"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Resposta `401` (credenciais inválidas):**
```json
{
  "success": false,
  "message": "E-mail ou senha incorretos."
}
```

---

### `POST /auth/logout` — Sair
**Requer token** · Invalida o token atual

**Resposta `200 OK`:**
```json
{
  "success": true,
  "message": "Logout realizado com sucesso."
}
```

---

### `GET /auth/me` — Dados do usuário logado
**Requer token**

**Resposta `200 OK`:**
```json
{
  "success": true,
  "message": "Operação realizada com sucesso.",
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@email.com",
    "level": "A1",
    "total_xp": 120,
    "streak_days": 3
  }
}
```

---

### `POST /auth/refresh` — Renovar token
**Requer token** · Gera um novo token antes de expirar

**Resposta `200 OK`:**
```json
{
  "success": true,
  "message": "Token atualizado com sucesso.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### `PUT /auth/profile` — Atualizar perfil
**Requer token**

**Body (todos opcionais):**
```json
{
  "name":               "João Santos",
  "native_language":    "pt",
  "target_language":    "en",
  "daily_goal_minutes": 30,
  "avatar":             "https://..."
}
```

---

### `PUT /auth/change-password` — Alterar senha
**Requer token**

**Body:**
```json
{
  "current_password":      "senhaatual",
  "password":              "novasenha123",
  "password_confirmation": "novasenha123"
}
```

---

## Idiomas

### `GET /languages` — Listar idiomas disponíveis
**Público**

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "English", "code": "en", "flag": "🇺🇸" },
    { "id": 2, "name": "Português", "code": "pt", "flag": "🇧🇷" }
  ]
}
```

---

## Lições

### `GET /lessons` — Listar lições
**Requer token**

**Query params opcionais:**
| Parâmetro | Tipo | Exemplo |
|-----------|------|---------|
| `level` | string | `A1`, `B1` |
| `category` | string | `greetings`, `travel` |
| `page` | int | `1` |

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Basic Greetings",
      "level": "A1",
      "category": "greetings",
      "order": 1,
      "xp_reward": 10,
      "total_phrases": 8,
      "is_completed": false,
      "is_locked": false,
      "progress_percent": 0
    }
  ]
}
```

### `GET /lessons/recommended` — Lições recomendadas
**Requer token** · Baseado no nível CEFR do usuário

### `GET /lessons/categories` — Categorias disponíveis
**Requer token**

### `GET /lessons/{id}` — Detalhe de uma lição
**Requer token**

---

## Exercícios

### `POST /exercises/lessons/{lesson}/start` — Iniciar lição
**Requer token** · Retorna a lista de exercícios da lição

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": {
    "exercises": [
      {
        "id": 1,
        "type": "multiple_choice",
        "question": "How do you say 'Olá' in English?",
        "options": ["Hello", "Goodbye", "Thanks", "Please"],
        "xp_reward": 5
      }
    ]
  }
}
```

### `POST /exercises/{exercise}/answer` — Responder exercício
**Requer token**

**Body:**
```json
{ "answer": "Hello" }
```

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": {
    "is_correct": true,
    "correct_answer": "Hello",
    "xp_earned": 5,
    "feedback": "Correto! Muito bem!"
  }
}
```

### `POST /exercises/sessions/{session}/complete` — Concluir sessão
**Requer token**

---

## Pronúncia

### `POST /pronunciation/analyze` — Analisar pronúncia ⭐ (recomendado)
**Requer token** · Analisa e salva automaticamente

**Body:**
```json
{
  "phrase_id":      1,
  "transcription":  "Hello, how are you?",
  "stt_confidence": 0.92,
  "audio_duration": 2.5,
  "audio_path":     "recordings/user_1_phrase_1.m4a"
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "message": "Análise de pronúncia concluída.",
  "data": {
    "score": {
      "id": 42,
      "composite_score": 84.50,
      "accuracy":    90.00,
      "fluency":     78.00,
      "confidence":  82.00,
      "grade":       "B",
      "accuracy_label":   "Excelente",
      "fluency_label":    "Bom",
      "confidence_label": "Bom",
      "weakest_metric": "fluency",
      "transcription":  "Hello, how are you?",
      "driver":         "mock",
      "processing_ms":  12,
      "created_at":     "2026-03-11T12:00:00.000000Z"
    },
    "feedback": {
      "summary":       "Boa pronúncia! Trabalhe no ritmo para soar mais natural.",
      "tips":          ["Fale em um ritmo constante, sem pausas longas entre as palavras."],
      "encouragement": "Muito bem! Você está quase lá! 💪",
      "metrics_labels": {
        "accuracy":   { "value": 90.0, "label": "Excelente", "name": "Precisão" },
        "fluency":    { "value": 78.0, "label": "Bom",       "name": "Fluência" },
        "confidence": { "value": 82.0, "label": "Bom",       "name": "Confiança" }
      }
    },
    "phrase_progress": {
      "phrase_id":    1,
      "attempts":     3,
      "best_score":   84.50,
      "latest_score": 84.50,
      "average_score":80.17
    }
  }
}
```

> **As três métricas explicadas:**
> | Métrica | Peso | Descrição |
> |---------|------|-----------|
> | `accuracy` | 50% | Precisão fonética dos sons |
> | `fluency` | 30% | Ritmo, velocidade e prosódia |
> | `confidence` | 20% | Clareza e projeção da voz |

### `POST /pronunciation/calculate-score` — Calcular score (preview)
**Requer token** · Calcula sem persistir no banco

**Body:**
```json
{
  "accuracy":   90.0,
  "fluency":    78.0,
  "confidence": 82.0
}
```

### `POST /pronunciation/score` — Enviar score manual (legado)
**Requer token** · Mantido por compatibilidade

### `GET /pronunciation/analysis` — Análise histórica completa
**Requer token**

### `GET /pronunciation/weekly-report` — Relatório semanal
**Requer token**

### `GET /pronunciation/history` — Histórico paginado
**Requer token** · Query: `?per_page=20`

### `GET /pronunciation/phrases` — Frases para praticar
**Requer token** · Query: `?limit=10`

### `GET /pronunciation/phrases/{phrase}/progress` — Progresso em uma frase
**Requer token**

---

## Diálogos

### `GET /dialogues` — Listar diálogos
**Requer token** · Query: `?topic=restaurant&level=A1`

### `GET /dialogues/topics` — Tópicos disponíveis
**Requer token**

**Tópicos cadastrados:**
| Slug | Nome |
|------|------|
| `greetings` | Cumprimentos |
| `restaurant` | Restaurante |
| `airport` | Aeroporto |
| `hotel` | Hotel |
| `shopping` | Compras |

### `GET /dialogues/random` — Diálogo aleatório
**Requer token** · Query: `?topic=greetings&level=A1`

### `GET /dialogues/{id}` — Detalhe do diálogo com linhas
**Requer token**

---

## Conversações

### `POST /conversations/start` — Iniciar conversa
**Requer token**

**Body:**
```json
{
  "topic":       "restaurant",
  "dialogue_id": 1
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "data": {
    "session": {
      "id": 7,
      "topic_slug": "restaurant",
      "status": "active",
      "total_lines": 10,
      "current_line_order": 0
    },
    "first_line": {
      "sender":       "app",
      "message":      "Good evening! Welcome to our restaurant.",
      "line_order":   1,
      "is_user_turn": false
    }
  }
}
```

### `GET /conversations/{session}/next-line` — Próxima linha
**Requer token**

### `POST /conversations/{session}/respond` — Enviar resposta do usuário
**Requer token**

**Body:**
```json
{ "response": "Good evening! A table for two, please." }
```

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": {
    "validation": {
      "is_correct":       true,
      "similarity_score": 0.91,
      "feedback":         "Excelente! 🎉"
    },
    "next_line": {
      "sender":       "app",
      "message":      "Of course! Smoking or non-smoking?",
      "is_user_turn": false
    },
    "xp_earned": 5,
    "session_completed": false
  }
}
```

### `POST /conversations/validate` — Validar resposta sem avançar
**Requer token**

**Body:**
```json
{
  "user_input":      "Good evening!",
  "expected_answer": "Good evening! Welcome."
}
```

### `POST /conversations/{session}/complete` — Finalizar conversa
**Requer token**

### `POST /conversations/{session}/abandon` — Abandonar conversa
**Requer token**

### `GET /conversations/{session}/history` — Histórico de mensagens
**Requer token**

### `GET /conversations` — Minhas sessões
**Requer token**

### `GET /conversations/topics/{topic}/history` — Histórico por tópico
**Requer token**

---

## Motor Pedagógico

### `GET /learning/progress` — Progresso completo do usuário
**Requer token**

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": {
    "level": {
      "code":        "A1",
      "description": "Iniciante",
      "rank":        1
    },
    "total_xp":              120,
    "xp_in_level":           120,
    "xp_to_next_level":      380,
    "level_progress_percent":24.0,
    "streak_days":           3,
    "lessons_completed":     5,
    "total_study_minutes":   47,
    "average_accuracy":      78.5
  }
}
```

### `GET /learning/next-level-estimate` — Estimativa para o próximo nível
**Requer token**

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": {
    "current_level":  "A1",
    "next_level":     "A2",
    "xp_remaining":   380,
    "daily_xp_needed":19,
    "estimated_days": 20
  }
}
```

### `GET /learning/next-lesson` — Próxima lição recomendada
**Requer token** · Baseado no nível e histórico do usuário

### `GET /learning/phrases-for-review` — Frases para revisão (Spaced Repetition)
**Requer token** · Algoritmo SM-2

### `POST /learning/phrases/{phrase}/review` — Registrar revisão
**Requer token**

**Body:**
```json
{ "quality": 4 }
```
> Qualidade: `0` = esqueceu completamente · `5` = resposta perfeita

### `GET /learning/cefr-levels` — Informações sobre todos os níveis CEFR
**Público (após autenticação)**

---

## Conquistas

### `GET /achievements` — Todas as conquistas disponíveis
**Requer token**

### `GET /achievements/mine` — Conquistas desbloqueadas pelo usuário
**Requer token**

---

## Sessões de Estudo

### `GET /sessions` — Histórico de sessões
**Requer token**

### `GET /sessions/weekly-report` — Relatório semanal de estudo
**Requer token**

### `GET /dashboard` — Dados consolidados do dashboard
**Requer token**

---

## Formato padrão das respostas

### Sucesso
```json
{
  "success": true,
  "message": "Operação realizada com sucesso.",
  "data":    { ... }
}
```

### Sucesso com paginação
```json
{
  "success": true,
  "message": "Listagem realizada com sucesso.",
  "data":    [ ... ],
  "meta": {
    "current_page": 1,
    "per_page":     15,
    "total":        48,
    "last_page":    4
  },
  "links": {
    "first": "http://localhost/.../lessons?page=1",
    "last":  "http://localhost/.../lessons?page=4",
    "prev":  null,
    "next":  "http://localhost/.../lessons?page=2"
  }
}
```

### Erro de validação `422`
```json
{
  "success": false,
  "message": "Dados inválidos.",
  "errors": {
    "email":    ["O campo email é obrigatório."],
    "password": ["Mínimo 8 caracteres."]
  }
}
```

### Não autenticado `401`
```json
{
  "success": false,
  "message": "Não autenticado. Forneça um token JWT válido."
}
```

### Recurso não encontrado `404`
```json
{
  "success": false,
  "message": "Recurso não encontrado."
}
```

---

## Códigos de erro

| Código | Significado |
|--------|-------------|
| `200` | Sucesso |
| `201` | Criado com sucesso |
| `401` | Não autenticado — token ausente, inválido ou expirado |
| `403` | Sem permissão para este recurso |
| `404` | Recurso não encontrado |
| `422` | Dados de entrada inválidos |
| `500` | Erro interno do servidor |

---

## Configuração do ambiente

```env
# .env — variáveis relevantes para a API

APP_URL=http://localhost/speakflow/public

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=speakflow
DB_USERNAME=postgres
DB_PASSWORD=

JWT_SECRET=seu_secret_aqui
JWT_ALGO=HS256
```

---

## Estrutura dos arquivos de autenticação

```
app/
├── Http/Controllers/API/
│   ├── AuthController.php        ← Login, registro, logout, me, refresh
│   └── BaseController.php        ← success(), error(), created(), paginated()
├── Models/
│   └── User.php                  ← implements JWTSubject
config/
├── auth.php                      ← guard 'api' com driver 'jwt'
└── jwt.php                       ← configurações do token (TTL, algoritmo)
routes/
└── api.php                       ← todas as rotas prefixadas com /api/v1
```

---

*Documentação gerada em 11/03/2026 — SpeakFlow v1.0*
