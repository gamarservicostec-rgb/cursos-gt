<?php
namespace Security;

/**
 * CSPManager
 * 
 * Gerencia a injeção robusta de cabeçalhos Content-Security-Policy (CSP)
 * protegendo contra XSS, Injeções e Clickjacking sem prejudicar bibliotecas externas autorizadas.
 */
class CSPManager {

    /**
     * Injeta os cabeçalhos de política de segurança de conteúdo correspondentes.
     */
    public static function injectCSP() {
        // Regras que permitem CDNs oficiais homologados no PRD
        $policies = [
            "default-src 'self'",
            
            // Permite scripts locais, inline necessários para Tailwind/configurações e o CDN do Tailwind
            "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com",
            
            // Permite estilos locais, inline e CDNs de fontes (Google Fonts e Clash Display)
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.cdnfonts.com https://api.fontshare.com",
            
            // Permite fontes do Google e fontshare
            "font-src 'self' https://fonts.gstatic.com https://fonts.cdnfonts.com https://api.fontshare.com",
            
            // Permite carregar imagens locais, avatares do Google e dados em base64 (para QR Code)
            "img-src 'self' data: https://lh3.googleusercontent.com https://contribution.usercontent.com https://*.usercontent.google.com",
            
            // Permite a incorporação do player de vídeo seguro da Bunny.net
            "frame-src 'self' https://iframe.mediadelivery.net",
            
            // Conexões de rede (APIs locais e Mercado Pago)
            "connect-src 'self' https://api.mercadopago.com"
        ];

        header("Content-Security-Policy: " . implode("; ", $policies) . ";");
    }
}
