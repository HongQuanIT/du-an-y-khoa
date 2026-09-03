<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use Modules\AiAssistant\Contracts\AiTutorClient;
use Modules\AiAssistant\Contracts\TutorReply;
use RuntimeException;

/**
 * Streams from the OpenAI Chat Completions API (SSE). Parsed chunks are handed
 * to $onDelta as they arrive; the full text + usage are returned at the end.
 */
final class OpenAiTutorClient implements AiTutorClient
{
    public function stream(string $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $payload = [
            'model' => config('aiassistant.tutor_model', 'gpt-4.1'),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'max_tokens' => (int) config('aiassistant.max_output_tokens', 900),
            'messages' => array_merge(
                [['role' => 'system', 'content' => $system]],
                array_map(static fn (array $m): array => [
                    'role' => $m['role'],
                    'content' => $m['content'],
                ], $messages),
            ),
        ];

        $client = new Client(['timeout' => (int) config('aiassistant.request_timeout', 60)]);

        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
            'stream' => true,
        ]);

        $body = $response->getBody();
        $full = '';
        $tokensIn = null;
        $tokensOut = null;
        $buffer = '';

        while (! $body->eof()) {
            if ($shouldStop !== null && $shouldStop()) {
                $body->close();

                return new TutorReply($full, [], $tokensIn, $tokensOut, stopped: true);
            }

            $buffer .= Utils::readLine($body);

            if (! str_contains($buffer, "\n")) {
                continue;
            }

            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines) ?? '';

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    break 2;
                }

                $decoded = json_decode($data, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $delta = (string) data_get($decoded, 'choices.0.delta.content', '');
                if ($delta !== '') {
                    $full .= $delta;
                    $onDelta($delta);
                }

                if (data_get($decoded, 'usage') !== null) {
                    $tokensIn = (int) data_get($decoded, 'usage.prompt_tokens', 0);
                    $tokensOut = (int) data_get($decoded, 'usage.completion_tokens', 0);
                }
            }
        }

        return new TutorReply($full, [], $tokensIn, $tokensOut);
    }
}
