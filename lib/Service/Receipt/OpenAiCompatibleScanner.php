<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Receipt;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Sends a receipt image to an OpenAI-compatible vision chat-completions endpoint
 * (DeepSeek, SiliconFlow) and returns the assistant's raw JSON content.
 *
 * @psalm-suppress UnusedClass
 */
class OpenAiCompatibleScanner {
	private const ENDPOINTS = [
		'deepseek' => 'https://api.deepseek.com/chat/completions',
		'siliconflow' => 'https://api.siliconflow.com/v1/chat/completions',
	];

	private const DEFAULT_MODELS = [
		'deepseek' => 'deepseek-v4-flash-vision-exp',
		'siliconflow' => 'Qwen/Qwen3-VL-8B-Instruct',
	];

	private IClientService $clientService;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IClientService $clientService, LoggerInterface $logger) {
		$this->clientService = $clientService;
		$this->logger = $logger;
	}

	/**
	 * @return string the assistant message content (the JSON object as a string)
	 */
	public function scan(
		string $provider,
		string $apiKey,
		string $model,
		string $imageBase64,
		string $mime,
		string $prompt,
		int $connectTimeout,
		int $readTimeout,
		int $maxTokens,
	): string {
		$url = self::ENDPOINTS[$provider] ?? null;
		if ($url === null) {
			throw new RuntimeException('Unsupported provider');
		}

		$resolvedModel = trim($model) === '' ? (self::DEFAULT_MODELS[$provider] ?? '') : trim($model);
		if ($resolvedModel === '') {
			throw new RuntimeException('No model configured for provider');
		}

		$body = [
			'model' => $resolvedModel,
			'messages' => [
				[
					'role' => 'user',
					'content' => [
						[
							'type' => 'image_url',
							'image_url' => [
								'url' => 'data:' . $mime . ';base64,' . $imageBase64,
								'detail' => 'low',
							],
						],
						['type' => 'text', 'text' => $prompt],
					],
				],
			],
			'response_format' => ['type' => 'json_object'],
			'max_tokens' => max(1, $maxTokens),
		];

		if ($provider === 'deepseek') {
			$body['thinking'] = ['type' => 'disabled'];
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey,
					'Content-Type' => 'application/json',
				],
				'body' => json_encode($body, JSON_UNESCAPED_SLASHES),
				'connect_timeout' => max(1, $connectTimeout),
				'timeout' => max(1, $readTimeout),
			]);

			$status = $response->getStatusCode();
			$responseBody = (string)$response->getBody();

			if ($status < 200 || $status >= 300) {
				$this->logger->warning('LLM provider returned an error', ['provider' => $provider, 'status' => $status]);
				throw new RuntimeException('Provider returned HTTP ' . $status);
			}

			$decoded = json_decode($responseBody, true);
			if (!is_array($decoded)) {
				throw new RuntimeException('Provider returned an invalid response');
			}

			/** @var array{choices?: list<array{message?: array{content?: string}}>} $decoded */
			$content = $decoded['choices'][0]['message']['content'] ?? null;
			if (!is_string($content) || trim($content) === '') {
				throw new RuntimeException('Provider returned empty content');
			}

			return $content;
		} catch (RuntimeException $e) {
			throw $e;
		} catch (\Exception $e) {
			$this->logger->error('Failed to scan receipt with LLM', ['exception' => $e, 'provider' => $provider]);
			throw new RuntimeException('Failed to reach the LLM provider', 0, $e);
		}
	}
}
