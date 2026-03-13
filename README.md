<div align="center">

# 🎙️ SpeakFlow

### Aprenda inglês de verdade — sem pagar caro para isso.

**SpeakFlow** é um aplicativo de aprendizado de idiomas de código aberto, construído para ser acessível a qualquer pessoa, especialmente aquelas que têm dificuldades de aprendizado ou não conseguem pagar pelos aplicativos pagos do mercado (Duolingo Plus, Babbel, Rosetta Stone, etc.).

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)](https://postgresql.org)
[![OpenAI](https://img.shields.io/badge/OpenAI-Whisper%20%7C%20GPT-412991?style=for-the-badge&logo=openai&logoColor=white)](https://openai.com)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

</div>

---

## 📖 Sobre o Projeto

Muitos aplicativos de aprendizado de idiomas são pagos, têm funcionalidades limitadas na versão gratuita ou simplesmente não se adaptam ao ritmo de quem tem dificuldades de aprendizado. O **SpeakFlow** nasceu para resolver isso.

### 🎯 Para quem é este aplicativo?

- Pessoas que **não podem pagar** por aplicativos premium de idiomas
- Estudantes com **dificuldades de aprendizado** (dislexia, TDAH, ansiedade, etc.) que precisam de mais repetição e paciência
- Quem quer aprender inglês de forma **progressiva e baseada em evidências**
- Desenvolvedores que querem **contribuir** com uma ferramenta educacional gratuita

### 💡 O que faz o SpeakFlow diferente?

| Funcionalidade | SpeakFlow | Apps pagos |
|---|---|---|
| Tutor de IA personalizado | ✅ Gratuito | 💰 Pago |
| Correção gramatical automática | ✅ Incluso | 💰 Premium |
| Transcrição de voz (Whisper) | ✅ Incluso | 💰 Premium |
| Repetição espaçada (SM-2) | ✅ Incluso | 💰 Alguns |
| Ranking semanal | ✅ Incluso | 💰 Alguns |
| Código aberto | ✅ Sim | ❌ Não |
| Autodirigido (sem ads) | ✅ Sim | ❌ Não |

---

## 🏗️ Arquitetura do Projeto

```
speakflow/
├── app/
│   ├── Contracts/              # Interfaces (ex: SpeechRecognitionContract)
│   ├── Http/
│   │   └── Controllers/API/    # 14 controllers REST
│   ├── Models/                 # 25 models Eloquent
│   ├── Services/               # Lógica de negócio desacoplada
│   │   ├── AiTutorService.php          # Tutor virtual com GPT-4o-mini
│   │   ├── VoiceTranscriptionService.php # Transcrição com Whisper
│   │   ├── LearningEngine.php          # Motor pedagógico (CEFR + SM-2)
│   │   ├── ConversationEngine.php      # Diálogos offline
│   │   ├── PronunciationAnalyzer.php   # Análise de pronúncia
│   │   ├── AchievementService.php      # Sistema de conquistas
│   │   ├── DailyActivityService.php    # Streak e atividade diária
│   │   └── LeaderboardService.php      # Ranking semanal
│   └── ValueObjects/           # CefrLevel, PronunciationResult
├── database/
│   ├── migrations/             # 20+ migrations organizadas
│   └── seeders/                # Dados iniciais (idiomas, lições, tópicos)
└── routes/
    └── api.php                 # 50+ endpoints REST versionados (/v1)
```

---

## 🛠️ Tecnologias Utilizadas

### Backend
| Tecnologia | Versão | Uso |
|---|---|---|
| [Laravel](https://laravel.com) | 12 | Framework principal |
| [PHP](https://php.net) | 8.3+ | Linguagem backend |
| [PostgreSQL](https://postgresql.org) | 16+ | Banco de dados principal |
| [JWT Auth](https://github.com/PHP-Open-Source-Saver/jwt-auth) | 2.x | Autenticação stateless |
| [OpenAI PHP](https://github.com/openai-php/client) | 0.19+ | Integração GPT-4o-mini e Whisper |

### Mobile (em desenvolvimento)
| Tecnologia | Uso |
|---|---|
| [Flutter](https://flutter.dev) | Framework mobile cross-platform |
| [Riverpod](https://riverpod.dev) | Gerenciamento de estado |
| [Dio](https://pub.dev/packages/dio) | Cliente HTTP com interceptors JWT |
| [Flutter Secure Storage](https://pub.dev/packages/flutter_secure_storage) | Armazenamento seguro do token |

---

## 🗄️ Banco de Dados

### Diagrama de Entidades Principais

```
users
  ├── user_lesson_progress    (progresso por lição)
  ├── user_exercise_attempts  (tentativas de exercícios + XP)
  ├── user_daily_activity     (atividade diária + streak)
  ├── user_weekly_xp          (ranking semanal)
  ├── user_achievements       (conquistas desbloqueadas)
  ├── pronunciation_scores    (análise de pronúncia)
  ├── phrase_review_states    (SM-2 / repetição espaçada)
  ├── study_sessions          (sessões de estudo)
  ├── ai_conversations        (conversas com tutor IA)
  │     ├── ai_messages       (mensagens da conversa)
  │     ├── ai_corrections    (correções gramaticais)
  │     └── ai_voice_messages (mensagens de voz transcritas)
  ├── ai_usage_logs           (controle de tokens e custo OpenAI)
  └── conversation_sessions   (sessões de diálogo offline)

languages → lessons → exercises
                    → phrases
dialogues → dialogue_lines
conversation_topics → ai_conversations
achievements → user_achievements
```

---

## 🚀 Como Instalar e Executar

### Pré-requisitos

- PHP 8.3+ com extensões: `pdo_pgsql`, `pgsql`, `sodium`, `fileinfo`
- PostgreSQL 14+
- Composer 2.x
- (Opcional) OpenAI API Key para IA real

### 1. Clone o repositório

```bash
git clone https://github.com/daniloMenesesEsme/speakflow.git
cd speakflow
```

### 2. Instale as dependências

```bash
composer install
```

### 3. Configure o ambiente

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edite o `.env` com suas configurações:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=speakflow
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Opcional — necessário para IA real (tutor virtual e transcrição de voz)
OPENAI_API_KEY=sk-...
```

### 4. Crie o banco de dados e execute as migrations

```bash
# Crie o banco no PostgreSQL
createdb speakflow

# Rode as migrations e seeders
php artisan migrate --seed
```

### 5. Inicie o servidor de desenvolvimento

```bash
php artisan serve
```

A API estará disponível em `http://127.0.0.1:8000/api/v1`

---

## 📡 Endpoints da API

Todos os endpoints retornam JSON no formato:
```json
{
  "success": true,
  "message": "Operação realizada com sucesso.",
  "data": { ... }
}
```

### 🔓 Autenticação (público)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/api/v1/auth/register` | Cadastro de novo usuário |
| `POST` | `/api/v1/auth/login` | Login — retorna JWT token |
| `GET`  | `/api/v1/languages` | Lista idiomas disponíveis |

### 🔒 Rotas Autenticadas (JWT Bearer Token)

#### Usuário e Perfil
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET`  | `/api/v1/auth/me` | Dados do usuário autenticado |
| `POST` | `/api/v1/auth/logout` | Logout (invalida token) |
| `POST` | `/api/v1/auth/refresh` | Renovar token JWT |
| `PUT`  | `/api/v1/auth/profile` | Atualizar perfil |
| `GET`  | `/api/v1/users/stats` | XP, streak, lições completas |
| `GET`  | `/api/v1/users/achievements` | Conquistas do usuário |

#### Conteúdo Educacional
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET`  | `/api/v1/lessons` | Lista lições (filtro por nível) |
| `GET`  | `/api/v1/lessons/{id}` | Detalhes de uma lição |
| `POST` | `/api/v1/lessons/{id}/complete` | Marcar lição como concluída |
| `GET`  | `/api/v1/exercises` | Lista exercícios |
| `POST` | `/api/v1/exercises/{id}/answer` | Responder exercício (+XP) |

#### Motor de Aprendizado (CEFR + SM-2)
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET`  | `/api/v1/learning/progress` | Progresso geral do aluno |
| `GET`  | `/api/v1/learning/next-lesson` | Próxima lição recomendada |
| `GET`  | `/api/v1/learning/phrases-for-review` | Frases para revisar (SM-2) |
| `GET`  | `/api/v1/learning/next-level-estimate` | Estimativa para próximo nível CEFR |
| `GET`  | `/api/v1/learning/cefr-levels` | Descrição dos níveis A1→C2 |

#### Tutor de IA
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/api/v1/ai/chat` | Enviar mensagem ao tutor (GPT-4o-mini) |
| `POST` | `/api/v1/ai/voice` | Enviar áudio — transcrição + resposta |
| `GET`  | `/api/v1/ai/voice` | Histórico de mensagens de voz |
| `GET`  | `/api/v1/ai/corrections` | Histórico de correções gramaticais |
| `GET`  | `/api/v1/ai/conversations` | Lista de conversas com o tutor |
| `GET`  | `/api/v1/ai/conversations/{id}` | Histórico de uma conversa |
| `GET`  | `/api/v1/ai/usage` | Tokens usados e custo estimado |

#### Tópicos de Conversa
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET`  | `/api/v1/conversation-topics` | 15 tópicos (filtro por nível CEFR) |
| `GET`  | `/api/v1/conversation-topics/{id}` | Detalhe + histórico do usuário |

#### Pronúncia
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/api/v1/pronunciation/analyze` | Analisar pronúncia (accuracy, fluency, confidence) |
| `GET`  | `/api/v1/pronunciation/history` | Histórico de avaliações |
| `GET`  | `/api/v1/pronunciation/phrases` | Frases para praticar pronúncia |

#### Diálogos Offline
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET`  | `/api/v1/dialogues` | Catálogo de diálogos |
| `POST` | `/api/v1/conversations/start` | Iniciar sessão de diálogo |
| `POST` | `/api/v1/conversations/{id}/respond` | Responder linha do diálogo |
| `GET`  | `/api/v1/conversations/{id}/next-line` | Próxima linha do diálogo |

#### Gamificação
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET`  | `/api/v1/leaderboard` | Top 50 usuários da semana |
| `GET`  | `/api/v1/leaderboard/me` | Posição do usuário no ranking |
| `GET`  | `/api/v1/achievements` | Todas as conquistas disponíveis |
| `GET`  | `/api/v1/users/achievements` | Conquistas desbloqueadas |

---

## 🧠 Funcionalidades Principais

### 1. Tutor Virtual com IA (`AiTutorService`)
- Conversas personalizadas com **GPT-4o-mini**
- Prompt adaptado ao nível CEFR do usuário (A1 a C2)
- Suporte a **15 tópicos de conversa** (alimentação, viagem, trabalho, etc.)
- Funciona **offline** com respostas contextuais quando sem API key
- Histórico completo de conversas salvo no banco

### 2. Correção Gramatical Automática (`AiTutorService.analyzeGrammar`)
- Detecta erros em **tempo real** durante a conversa
- Com API key: análise via OpenAI (qualquer tipo de erro)
- **Offline**: detecta os 7 erros mais comuns de falantes de português
- Retorna texto original, corrigido e explicação educativa

### 3. Transcrição de Voz (`VoiceTranscriptionService`)
- Suporte a **Whisper-1** da OpenAI para transcrição precisa
- Aceita: `mp3`, `wav`, `webm`, `ogg`, `m4a`, `flac` (até 25MB)
- Detecção automática de idioma
- Após transcrição, envia texto ao tutor de IA para resposta
- **Mock offline** para testes sem API key

### 4. Motor Pedagógico (`LearningEngine`)
- Níveis **CEFR**: A1, A2, B1, B2, C1, C2
- Algoritmo **SM-2** de Repetição Espaçada (Spaced Repetition)
- Cálculo de progresso, XP e estimativa de tempo para próximo nível
- Seleção inteligente da próxima lição e frases para revisão

### 5. Análise de Pronúncia (`PronunciationAnalyzer`)
- Três métricas: **accuracy**, **fluency**, **confidence**
- Feedback personalizado em texto
- Histórico de evolução por frase
- Preparado para integração com Azure Speech Services

### 6. Sistema de Gamificação
- **XP**: pontos por exercícios e lições completadas
- **Streak**: dias consecutivos de estudo (reinicia se faltar um dia)
- **Conquistas**: 14 conquistas desbloqueáveis por marcos
- **Ranking semanal**: top 50 usuários por XP da semana
- **Anti-farming**: XP concedido apenas na primeira resposta correta

### 7. Diálogos Offline (`ConversationEngine`)
- Diálogos pré-cadastrados por tema (restaurante, aeroporto, hotel, etc.)
- Validação de respostas com similaridade textual (Levenshtein)
- Histórico de sessões e progresso por diálogo

---

## 🏆 Conquistas Disponíveis

| Conquista | Condição | XP |
|-----------|----------|----|
| Primeiro Exercício | Responder 1 exercício | 10 XP |
| Primeira Lição | Completar 1 lição | 20 XP |
| Primeiros Pontos | Atingir 50 XP | 10 XP |
| Streak 3 Dias | 3 dias seguidos | 30 XP |
| Streak 7 Dias | 7 dias seguidos | 50 XP |
| Praticante | 10 exercícios corretos | 25 XP |
| e mais... | — | — |

---

## 🎯 Tópicos de Conversa com IA

Distribuídos por nível CEFR, do iniciante ao avançado:

| Nível | Tópicos |
|-------|---------|
| **A1** | Greetings & Introductions, Numbers & Colors, Family |
| **A2** | Food & Restaurant, Daily Routine, Shopping |
| **B1** | Travel & Transport, Work & Career, School & Education, Health & Body |
| **B2** | Movies & Entertainment, Technology & Social Media, Environment & Nature |
| **C1** | Business & Economics, Politics & Society |

---

## 🔧 Modo Offline vs. Online

O SpeakFlow foi projetado para funcionar **com ou sem** conexão com a OpenAI:

| Funcionalidade | Sem API Key (Offline) | Com API Key (Online) |
|---|---|---|
| Tutor de IA | ✅ Respostas contextuais | ✅ GPT-4o-mini completo |
| Correção gramatical | ✅ 7 regras (PT→EN) | ✅ Qualquer erro |
| Transcrição de voz | ✅ Mock simulado | ✅ Whisper-1 real |
| Exercícios | ✅ Completo | ✅ Completo |
| Lições/Diálogos | ✅ Completo | ✅ Completo |
| Gamificação | ✅ Completo | ✅ Completo |

---

## 🤝 Como Contribuir

Contribuições são muito bem-vindas! Este é um projeto para a comunidade.

```bash
# 1. Fork o repositório
# 2. Crie sua branch
git checkout -b feature/minha-feature

# 3. Commit suas mudanças
git commit -m "feat: adiciona minha feature"

# 4. Push para o fork
git push origin feature/minha-feature

# 5. Abra um Pull Request
```

### Áreas que precisam de ajuda
- 🌐 Tradução para outros idiomas
- 📱 App Flutter (mobile)
- 🧪 Testes automatizados (PHPUnit/Pest)
- 📝 Mais lições e exercícios no banco de dados
- 🎨 Design do aplicativo mobile
- ♿ Acessibilidade para pessoas com deficiências visuais

---

## 📄 Licença

Este projeto é distribuído sob a licença **MIT**. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

Isso significa que você pode usar, modificar e distribuir livremente — inclusive para fins comerciais.

---

## 👨‍💻 Autor

Desenvolvido com ❤️ por **Danilo Meneses**

- GitHub: [@daniloMenesesEsme](https://github.com/daniloMenesesEsme)

---

<div align="center">

**Se este projeto te ajudou, deixe uma ⭐ no repositório!**

*"Educação de qualidade não deveria ser um privilégio de quem pode pagar."*

</div>
