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
     * Envia uma mensagem de texto simples para um número de WhatsApp.
     * 
     * @param string $phone Número no formato DDI + DDD + Número (ex: 5511999998888)
     * @param string $message Conteúdo textual da mensagem
     * @return array Resposta da requisição HTTP contendo status de envio
     */
    public static function sendMessage($phone, $message) {
        // Limpa formatações do número para manter apenas dígitos
        $phone = preg_replace('/\D/', '', $phone);

        // Se o número não contiver DDI Brasil, adiciona por padrão
        if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }

        $payload = [
            'to' => $phone,
            'message' => $message
        ];

        $ch = curl_init(AppConfig::$WHATSAPP_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AppConfig::$WHATSAPP_API_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Permite bypass em ambiente local sem SSL configurado
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
}
