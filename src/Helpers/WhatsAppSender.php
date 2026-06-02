<?php
namespace Helpers;

use Config\AppConfig;

require_once __DIR__ . '/../Config/AppConfig.php';

/**
 * WhatsAppSender
 * 
 * Helper responsável por encapsular disparos de mensagens e alertas do sistema
 * utilizando a API do WhatsApp via canal Discloud.
 */
class WhatsAppSender {

    /**
     * Extrai apenas a URL base (host) da API do WhatsApp configurada no env.
     * 
     * @return string URL base limpa (ex: https://bot-eloha.discloud.app)
     */
    private static function getBaseUrl() {
        $url = AppConfig::$WHATSAPP_API_URL;
        $urlParts = parse_url($url);
        $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : 'https://';
        $host = isset($urlParts['host']) ? $urlParts['host'] : 'bot-eloha.discloud.app';
        return $scheme . $host;
    }

    /**
     * Dispara um evento de compra (purchase) que ativa o fluxo automático na Eloha Bots SaaS.
     * 
     * @param string $phone Número do telefone do aluno
     * @param string $studentName Nome completo do aluno
     * @param string $courseTitle Título do curso comprado
     * @param float $price Valor do pagamento
     * @param string $studentEmail E-mail do aluno
     * @return array Resposta da requisição HTTP
     */
    public static function sendPurchaseEvent($phone, $studentName, $courseTitle, $price, $studentEmail) {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }

        $baseUrl = self::getBaseUrl();
        $url = $baseUrl . '/api/events/purchase';

        $payload = [
            'name' => $studentName,
            'phone' => $phone,
            'product' => $courseTitle,
            'value' => (float)$price,
            'data' => [
                'course_title' => $courseTitle,
                'email' => $studentEmail
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AppConfig::$WHATSAPP_API_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (AppConfig::$DEV_MODE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => "Erro cURL: " . $error
            ];
        }

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $result
            ];
        }

        return [
            'success' => false,
            'status_code' => $httpCode,
            'response' => $result
        ];
    }

    /**
     * Envia uma mensagem avulsa individual para um número usando a primeira instância conectada disponível.
     * 
     * @param string $phone Número no formato DDI + DDD + Número (ex: 5511999998888)
     * @param string $message Conteúdo textual da mensagem
     * @return array Resposta da requisição HTTP contendo status de envio
     */
    public static function sendMessage($phone, $message) {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }

        $baseUrl = self::getBaseUrl();

        // 1. Passo 1: Listar instâncias para encontrar a conectada ativa
        $instancesUrl = $baseUrl . '/api/instances';
        $ch = curl_init($instancesUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . AppConfig::$WHATSAPP_API_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (AppConfig::$DEV_MODE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => "Erro ao obter instâncias (cURL): " . $error
            ];
        }

        $result = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300 || !isset($result['instances'])) {
            $errMsg = isset($result['error']) ? $result['error'] : 'Erro desconhecido';
            return [
                'success' => false,
                'message' => "Falha ao obter instâncias do bot (HTTP {$httpCode}): " . $errMsg,
                'response' => $result
            ];
        }

        $instanceId = null;
        foreach ($result['instances'] as $inst) {
            if ($inst['status'] === 'connected') {
                $instanceId = $inst['id'];
                break;
            }
        }

        if (!$instanceId) {
            return [
                'success' => false,
                'message' => "Nenhuma instância de WhatsApp conectada ativa. Por favor, conecte o QR Code no painel Eloha Bots antes de enviar."
            ];
        }

        // 2. Passo 2: Enviar mensagem individual via API
        $sendUrl = $baseUrl . '/api/messages/send';
        $payload = [
            'instance_id' => $instanceId,
            'to' => $phone,
            'message' => $message
        ];

        $ch = curl_init($sendUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AppConfig::$WHATSAPP_API_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (AppConfig::$DEV_MODE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => "Erro ao enviar mensagem (cURL): " . $error
            ];
        }

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $result
            ];
        }

        $errMsg = isset($result['error']) ? $result['error'] : 'Erro desconhecido ao processar envio';
        return [
            'success' => false,
            'status_code' => $httpCode,
            'message' => "Erro da API do bot: " . $errMsg,
            'response' => $result
        ];
    }
}
