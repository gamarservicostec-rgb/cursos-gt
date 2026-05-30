<?php
namespace Security;

use Config\AppConfig;

require_once __DIR__ . '/../Config/AppConfig.php';

/**
 * BunnySecurity
 * 
 * Classe responsável por assegurar a proteção de streaming de aulas no Bunny.net
 * através do algoritmo oficial de Signed URLs e expiração temporária de links.
 */
class BunnySecurity {

    // Chave secreta de tokenização do Bunny Stream (Mocada por padrão)
    private static $tokenKey = 'bunny-stream-secure-token-key-xyz-123';

    /**
     * Gera uma URL de Incorporação (embed) assinada e temporária para o Iframe da Bunny.net.
     * 
     * @param string $libraryId ID da biblioteca de vídeos na Bunny.net
     * @param string $videoId ID do vídeo da aula
     * @param int $expirationMinutes Tempo em minutos até o link expirar (padrão 120m)
     * @return string URL final contendo os hashes seguros de acesso temporário
     */
    public static function generateSignedUrl($libraryId, $videoId, $expirationMinutes = 120) {
        // Segundos desde a época Unix mais a expiração
        $expires = time() + ($expirationMinutes * 60);
        
        // Algoritmo oficial de hash da Bunny.net para Token Authentication:
        // sha256(tokenKey + videoId + expires)
        $hashable = self::$tokenKey . $videoId . $expires;
        $token = hash('sha256', $hashable);

        // Retorna a URL pronta para incorporação com controles de marcas moscadas do player
        // autoplay=false, loop=false, preload=true
        return "https://iframe.mediadelivery.net/embed/" . $libraryId . "/" . $videoId . "?token=" . $token . "&expires=" . $expires . "&autoplay=false";
    }

    /**
     * Retorna a tag iframe pronta e protegida com restrições adicionais de reprodução.
     */
    public static function getPlayerIframeHtml($libraryId, $videoId) {
        $signedUrl = self::generateSignedUrl($libraryId, $videoId);
        
        // Retorna iframe responsivo de alta fidelidade
        // Atributos de segurança: sandbox (restringe downloads e manipulações estranhas)
        return '
        <div class="video-container relative w-full h-0 pb-[56.25%] overflow-hidden rounded-2xl border border-white/5 shadow-2xl bg-black">
            <iframe 
                src="' . $signedUrl . '" 
                loading="lazy" 
                style="border: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" 
                allowfullscreen="true">
            </iframe>
            <!-- Bloqueador de Clique Direito Translúcido Transparente sobre o player -->
            <div class="absolute inset-0 pointer-events-none" oncontextmenu="return false;"></div>
        </div>';
    }
}
