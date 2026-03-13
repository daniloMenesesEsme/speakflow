<?php

namespace Database\Seeders;

use App\Models\Dialogue;
use App\Models\DialogueLine;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DialogueSeeder extends Seeder
{
    public function run(): void
    {
        $english = Language::where('code', 'en')->first();

        if (!$english) {
            $this->command->warn('Idioma "en" não encontrado. Rode o LanguageSeeder primeiro.');
            return;
        }

        foreach ($this->dialoguesData() as $data) {
            $linesData = $data['lines'];
            unset($data['lines']);

            $data['language_id'] = $english->id;
            $data['slug']        = $data['slug'] ?? Str::slug($data['topic']);
            $data['is_active']   = true;

            $dialogue = Dialogue::firstOrCreate(
                ['language_id' => $english->id, 'slug' => $data['slug'], 'level' => $data['level']],
                $data
            );

            foreach ($linesData as $lineData) {
                DialogueLine::firstOrCreate(
                    ['dialogue_id' => $dialogue->id, 'order' => $lineData['order']],
                    array_merge($lineData, ['dialogue_id' => $dialogue->id])
                );
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════

    private function dialoguesData(): array
    {
        return [

            // ─── GREETINGS ─────────────────────────────────────────────────
            [
                'topic'             => 'Greetings and Introductions',
                'slug'              => 'greetings',
                'topic_category'    => 'social',
                'level'             => 'A1',
                'description'       => 'Practice basic greetings and introducing yourself.',
                'context'           => 'You meet someone for the first time at a party.',
                'estimated_minutes' => 4,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'Alex',  'text' => 'Hi! I\'m Alex. Nice to meet you!',
                     'is_user_turn' => false, 'translation' => 'Oi! Sou Alex. Prazer em conhecer você!'],
                    ['order' => 2, 'speaker' => 'You',   'text' => 'Hi Alex! I\'m [name]. Nice to meet you too!',
                     'expected_answer' => 'nice to meet you', 'is_user_turn' => true,
                     'translation' => 'Oi Alex! Sou [nome]. Prazer igualmente!',
                     'hints' => ['Say: Nice to meet you too!', 'Use "I\'m" to introduce yourself.']],
                    ['order' => 3, 'speaker' => 'Alex',  'text' => 'Where are you from?',
                     'is_user_turn' => false, 'translation' => 'De onde você é?'],
                    ['order' => 4, 'speaker' => 'You',   'text' => 'I\'m from Brazil. And you?',
                     'expected_answer' => 'I\'m from Brazil', 'is_user_turn' => true,
                     'translation' => 'Sou do Brasil. E você?',
                     'hints' => ['Start with: I\'m from...']],
                    ['order' => 5, 'speaker' => 'Alex',  'text' => 'I\'m from Canada. How long have you been here?',
                     'is_user_turn' => false, 'translation' => 'Sou do Canadá. Há quanto tempo você está aqui?'],
                    ['order' => 6, 'speaker' => 'You',   'text' => 'Just two weeks. I\'m still learning my way around.',
                     'expected_answer' => 'two weeks', 'is_user_turn' => true,
                     'translation' => 'Só duas semanas. Ainda estou me adaptando.',
                     'hints' => ['Use numbers to express time: two weeks']],
                ],
            ],

            // ─── RESTAURANT ────────────────────────────────────────────────
            [
                'topic'             => 'At the Restaurant',
                'slug'              => 'restaurant',
                'topic_category'    => 'daily_life',
                'level'             => 'A1',
                'description'       => 'Learn how to order food and drinks at a restaurant.',
                'context'           => 'You sit down at a restaurant and a waiter comes to your table.',
                'estimated_minutes' => 5,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'Waiter', 'text' => 'Good evening! Welcome. Do you have a reservation?',
                     'is_user_turn' => false, 'translation' => 'Boa noite! Bem-vindo. Você tem reserva?'],
                    ['order' => 2, 'speaker' => 'You',    'text' => 'No, I don\'t. Is there a table for two available?',
                     'expected_answer' => 'table for two', 'is_user_turn' => true,
                     'translation' => 'Não, não tenho. Há mesa para dois disponível?',
                     'hints' => ['Ask for: a table for two', 'Use "Is there...?" for availability']],
                    ['order' => 3, 'speaker' => 'Waiter', 'text' => 'Of course! Right this way. Here is the menu.',
                     'is_user_turn' => false, 'translation' => 'Claro! Por aqui. Aqui está o cardápio.'],
                    ['order' => 4, 'speaker' => 'You',    'text' => 'Thank you. What do you recommend?',
                     'expected_answer' => 'What do you recommend', 'is_user_turn' => true,
                     'translation' => 'Obrigado. O que você recomenda?',
                     'hints' => ['Ask: What do you recommend?']],
                    ['order' => 5, 'speaker' => 'Waiter', 'text' => 'Our grilled salmon is excellent tonight!',
                     'is_user_turn' => false, 'translation' => 'Nosso salmão grelhado está excelente hoje à noite!'],
                    ['order' => 6, 'speaker' => 'You',    'text' => 'I\'ll have the grilled salmon, please.',
                     'expected_answer' => 'I\'ll have the grilled salmon', 'is_user_turn' => true,
                     'translation' => 'Vou querer o salmão grelhado, por favor.',
                     'hints' => ['Use "I\'ll have..." to order food']],
                    ['order' => 7, 'speaker' => 'Waiter', 'text' => 'Excellent choice! And to drink?',
                     'is_user_turn' => false, 'translation' => 'Excelente escolha! E para beber?'],
                    ['order' => 8, 'speaker' => 'You',    'text' => 'Just water, please.',
                     'expected_answer' => 'Just water, please', 'is_user_turn' => true,
                     'translation' => 'Só água, por favor.',
                     'hints' => ['Keep it simple: Just water, please.']],
                    ['order' => 9, 'speaker' => 'Waiter', 'text' => 'Your order will be ready shortly.',
                     'is_user_turn' => false, 'translation' => 'Seu pedido estará pronto em breve.'],
                ],
            ],

            // ─── RESTAURANT (B1) ───────────────────────────────────────────
            [
                'topic'             => 'Dealing with Problems at a Restaurant',
                'slug'              => 'restaurant',
                'topic_category'    => 'daily_life',
                'level'             => 'B1',
                'description'       => 'Practice handling issues and making complaints at a restaurant.',
                'context'           => 'Your food arrived cold and the order was wrong.',
                'estimated_minutes' => 6,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'You',    'text' => 'Excuse me, I think there\'s a problem with my order.',
                     'expected_answer' => 'problem with my order', 'is_user_turn' => true,
                     'translation' => 'Com licença, acho que há um problema com meu pedido.',
                     'hints' => ['Use: Excuse me, I think there\'s a problem...']],
                    ['order' => 2, 'speaker' => 'Waiter', 'text' => 'I\'m sorry to hear that. What seems to be the issue?',
                     'is_user_turn' => false, 'translation' => 'Sinto muito ouvir isso. Qual parece ser o problema?'],
                    ['order' => 3, 'speaker' => 'You',    'text' => 'I ordered the salmon, but this is chicken. Also, it\'s cold.',
                     'expected_answer' => 'ordered the salmon but this is chicken', 'is_user_turn' => true,
                     'translation' => 'Pedi o salmão, mas isto é frango. Além disso, está frio.',
                     'hints' => ['List the problems clearly: wrong item + cold food']],
                    ['order' => 4, 'speaker' => 'Waiter', 'text' => 'I sincerely apologize for that. I\'ll bring the correct dish immediately.',
                     'is_user_turn' => false, 'translation' => 'Peço sinceras desculpas. Trarei o prato correto imediatamente.'],
                    ['order' => 5, 'speaker' => 'You',    'text' => 'I appreciate that. Could we also get a discount?',
                     'expected_answer' => 'Could we get a discount', 'is_user_turn' => true,
                     'translation' => 'Agradeço. Poderíamos ter um desconto?',
                     'hints' => ['Use "Could we...?" for polite requests']],
                ],
            ],

            // ─── AIRPORT ───────────────────────────────────────────────────
            [
                'topic'             => 'At the Airport',
                'slug'              => 'airport',
                'topic_category'    => 'travel',
                'level'             => 'A2',
                'description'       => 'Practice checking in and navigating an airport.',
                'context'           => 'You arrive at the airport check-in counter.',
                'estimated_minutes' => 6,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'Agent', 'text' => 'Good morning! May I see your passport and ticket, please?',
                     'is_user_turn' => false, 'translation' => 'Bom dia! Posso ver seu passaporte e passagem, por favor?'],
                    ['order' => 2, 'speaker' => 'You',   'text' => 'Of course. Here you go.',
                     'expected_answer' => 'Here you go', 'is_user_turn' => true,
                     'translation' => 'Claro. Aqui está.',
                     'hints' => ['Hand over documents: Here you go.']],
                    ['order' => 3, 'speaker' => 'Agent', 'text' => 'Thank you. Do you have any bags to check?',
                     'is_user_turn' => false, 'translation' => 'Obrigado. Tem alguma bagagem para despachar?'],
                    ['order' => 4, 'speaker' => 'You',   'text' => 'Yes, I have one suitcase to check.',
                     'expected_answer' => 'one suitcase to check', 'is_user_turn' => true,
                     'translation' => 'Sim, tenho uma mala para despachar.',
                     'hints' => ['Use numbers: one suitcase, two bags...']],
                    ['order' => 5, 'speaker' => 'Agent', 'text' => 'Would you prefer a window or aisle seat?',
                     'is_user_turn' => false, 'translation' => 'Prefere janela ou corredor?'],
                    ['order' => 6, 'speaker' => 'You',   'text' => 'A window seat, please.',
                     'expected_answer' => 'window seat', 'is_user_turn' => true,
                     'translation' => 'Janela, por favor.',
                     'hints' => ['window seat or aisle seat']],
                    ['order' => 7, 'speaker' => 'Agent', 'text' => 'Your gate is B12. Boarding begins in 45 minutes.',
                     'is_user_turn' => false, 'translation' => 'Seu portão é B12. Embarque começa em 45 minutos.'],
                    ['order' => 8, 'speaker' => 'You',   'text' => 'Where is gate B12?',
                     'expected_answer' => 'Where is gate B12', 'is_user_turn' => true,
                     'translation' => 'Onde fica o portão B12?',
                     'hints' => ['Ask for directions: Where is...?']],
                    ['order' => 9, 'speaker' => 'Agent', 'text' => 'Turn left and follow the signs. Have a safe flight!',
                     'is_user_turn' => false, 'translation' => 'Vire à esquerda e siga as placas. Bom voo!'],
                ],
            ],

            // ─── HOTEL ─────────────────────────────────────────────────────
            [
                'topic'             => 'Checking In at a Hotel',
                'slug'              => 'hotel',
                'topic_category'    => 'travel',
                'level'             => 'A2',
                'description'       => 'Practice checking in and asking for hotel amenities.',
                'context'           => 'You arrive at the hotel reception after a long trip.',
                'estimated_minutes' => 5,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'Receptionist', 'text' => 'Welcome to the Grand Hotel! Do you have a reservation?',
                     'is_user_turn' => false, 'translation' => 'Bem-vindo ao Grand Hotel! Você tem reserva?'],
                    ['order' => 2, 'speaker' => 'You',           'text' => 'Yes, I have a reservation under Johnson.',
                     'expected_answer' => 'reservation under', 'is_user_turn' => true,
                     'translation' => 'Sim, tenho uma reserva no nome Johnson.',
                     'hints' => ['Say: I have a reservation under [your name]']],
                    ['order' => 3, 'speaker' => 'Receptionist', 'text' => 'Let me check... Yes, a double room for three nights. Is that correct?',
                     'is_user_turn' => false, 'translation' => 'Deixa-me verificar... Sim, quarto duplo por três noites. Está correto?'],
                    ['order' => 4, 'speaker' => 'You',           'text' => 'Yes, that\'s correct.',
                     'expected_answer' => 'Yes, that\'s correct', 'is_user_turn' => true,
                     'translation' => 'Sim, está correto.',
                     'hints' => ['Confirm: Yes, that\'s correct.']],
                    ['order' => 5, 'speaker' => 'Receptionist', 'text' => 'Great. May I have your credit card for incidentals?',
                     'is_user_turn' => false, 'translation' => 'Ótimo. Posso ter seu cartão de crédito para despesas adicionais?'],
                    ['order' => 6, 'speaker' => 'You',           'text' => 'Sure. Does the room have Wi-Fi?',
                     'expected_answer' => 'Does the room have Wi-Fi', 'is_user_turn' => true,
                     'translation' => 'Claro. O quarto tem Wi-Fi?',
                     'hints' => ['Ask about amenities: Does the room have...?']],
                    ['order' => 7, 'speaker' => 'Receptionist', 'text' => 'Yes, complimentary Wi-Fi throughout the hotel. Checkout is at 11 AM.',
                     'is_user_turn' => false, 'translation' => 'Sim, Wi-Fi gratuito em todo o hotel. O checkout é às 11h.'],
                    ['order' => 8, 'speaker' => 'You',           'text' => 'Thank you. Could you send someone to help with my luggage?',
                     'expected_answer' => 'Could you send someone', 'is_user_turn' => true,
                     'translation' => 'Obrigado. Poderia mandar alguém para ajudar com minha bagagem?',
                     'hints' => ['Request help: Could you send someone...?']],
                ],
            ],

            // ─── SHOPPING ──────────────────────────────────────────────────
            [
                'topic'             => 'Shopping for Clothes',
                'slug'              => 'shopping',
                'topic_category'    => 'daily_life',
                'level'             => 'A1',
                'description'       => 'Practice asking for help and sizes when shopping for clothes.',
                'context'           => 'You are in a clothing store looking for a jacket.',
                'estimated_minutes' => 5,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'Staff', 'text' => 'Hi there! Can I help you with anything today?',
                     'is_user_turn' => false, 'translation' => 'Olá! Posso ajudá-lo com algo hoje?'],
                    ['order' => 2, 'speaker' => 'You',   'text' => 'Yes, I\'m looking for a jacket.',
                     'expected_answer' => 'looking for a jacket', 'is_user_turn' => true,
                     'translation' => 'Sim, estou procurando uma jaqueta.',
                     'hints' => ['Use: I\'m looking for...']],
                    ['order' => 3, 'speaker' => 'Staff', 'text' => 'What size are you?',
                     'is_user_turn' => false, 'translation' => 'Qual é o seu tamanho?'],
                    ['order' => 4, 'speaker' => 'You',   'text' => 'I\'m a medium. Do you have it in blue?',
                     'expected_answer' => 'medium', 'is_user_turn' => true,
                     'translation' => 'Sou médio. Vocês têm em azul?',
                     'hints' => ['State your size: I\'m a medium / large / small']],
                    ['order' => 5, 'speaker' => 'Staff', 'text' => 'Let me check the back. We have it in navy blue.',
                     'is_user_turn' => false, 'translation' => 'Deixe-me verificar no estoque. Temos em azul-marinho.'],
                    ['order' => 6, 'speaker' => 'You',   'text' => 'Can I try it on?',
                     'expected_answer' => 'Can I try it on', 'is_user_turn' => true,
                     'translation' => 'Posso experimentar?',
                     'hints' => ['Ask: Can I try it on?']],
                    ['order' => 7, 'speaker' => 'Staff', 'text' => 'Of course! The fitting rooms are over there.',
                     'is_user_turn' => false, 'translation' => 'Claro! Os provadores estão ali.'],
                    ['order' => 8, 'speaker' => 'You',   'text' => 'It fits perfectly. How much is it?',
                     'expected_answer' => 'How much is it', 'is_user_turn' => true,
                     'translation' => 'Ficou perfeito. Quanto custa?',
                     'hints' => ['Ask price: How much is it?']],
                    ['order' => 9, 'speaker' => 'Staff', 'text' => 'It\'s fifty-nine ninety-nine.',
                     'is_user_turn' => false, 'translation' => 'São cinquenta e nove vírgula noventa e nove.'],
                    ['order' => 10, 'speaker' => 'You',  'text' => 'I\'ll take it!',
                     'expected_answer' => "I'll take it", 'is_user_turn' => true,
                     'translation' => 'Vou levar!',
                     'hints' => ['To buy: I\'ll take it!']],
                ],
            ],

            // ─── SHOPPING (B1) ─────────────────────────────────────────────
            [
                'topic'             => 'Returning an Item',
                'slug'              => 'shopping',
                'topic_category'    => 'daily_life',
                'level'             => 'B1',
                'description'       => 'Practice returning a defective product to a store.',
                'context'           => 'You bought a phone last week and it stopped working.',
                'estimated_minutes' => 6,
                'lines'             => [
                    ['order' => 1, 'speaker' => 'You',   'text' => 'Excuse me. I bought this phone here last week and it stopped working.',
                     'expected_answer' => 'stopped working', 'is_user_turn' => true,
                     'translation' => 'Com licença. Comprei este telefone aqui na semana passada e parou de funcionar.',
                     'hints' => ['Explain the issue: it stopped working']],
                    ['order' => 2, 'speaker' => 'Staff', 'text' => 'I\'m sorry to hear that. Do you have your receipt?',
                     'is_user_turn' => false, 'translation' => 'Sinto muito ouvir isso. Você tem o recibo?'],
                    ['order' => 3, 'speaker' => 'You',   'text' => 'Yes, here it is. I\'d like a refund or exchange.',
                     'expected_answer' => 'refund or exchange', 'is_user_turn' => true,
                     'translation' => 'Sim, aqui está. Gostaria de reembolso ou troca.',
                     'hints' => ['State what you want: refund or exchange']],
                    ['order' => 4, 'speaker' => 'Staff', 'text' => 'I\'ll need to inspect the phone first. Please wait a moment.',
                     'is_user_turn' => false, 'translation' => 'Precisarei inspecionar o telefone primeiro. Por favor, aguarde um momento.'],
                    ['order' => 5, 'speaker' => 'You',   'text' => 'Of course. How long will it take?',
                     'expected_answer' => 'How long will it take', 'is_user_turn' => true,
                     'translation' => 'Claro. Quanto tempo vai demorar?',
                     'hints' => ['Ask about time: How long will it take?']],
                ],
            ],
        ];
    }
}
