<?php
namespace Helpers;

use Config\AppConfig;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../Config/AppConfig.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * EmailSender
 * 
 * Helper responsável pelo gerenciamento e envio de e-mails transacionais.
 * Fornece templates pré-formatados com a paleta visual do Neon Amber Fusion.
 */
class EmailSender {

    /**
     * Envia um e-mail formatado em HTML para o destinatário usando PHPMailer SMTP.
     * 
     * @param string $to E-mail do destinatário
     * @param string $subject Assunto da mensagem
     * @param string $bodyHtml Corpo em formato HTML
     * @return bool Retorna verdadeiro se o e-mail foi disparado
     */
    public static function send($to, $subject, $bodyHtml) {
        $mail = new PHPMailer(true);

        try {
            // Configurações do Servidor SMTP do Gmail
            $mail->isSMTP();
            $mail->Host       = AppConfig::$SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = AppConfig::$SMTP_USER;
            $mail->Password   = AppConfig::$SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = AppConfig::$SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Destinatários
            $mail->setFrom(AppConfig::$SMTP_USER, 'GT Cursos Suporte');
            $mail->addAddress($to);
            $mail->addReplyTo(AppConfig::$SMTP_USER, 'GT Cursos Suporte');

            // Conteúdo do E-mail
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = strip_tags($bodyHtml);

            return $mail->send();
        } catch (Exception $e) {
            error_log("Falha ao enviar e-mail via PHPMailer: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Gera o template visual completo padrão do sistema.
     * 
     * @param string $title Título interno do corpo do e-mail
     * @param string $content HTML com o conteúdo interno
     * @param string|null $buttonText Texto do botão de ação principal (opcional)
     * @param string|null $buttonUrl Link do botão de ação principal (opcional)
     * @return string Estrutura HTML final pronta para envio
     */
    public static function getTemplateHtml($title, $content, $buttonText = null, $buttonUrl = null) {
        $buttonHtml = '';
        if ($buttonText && $buttonUrl) {
            $buttonHtml = '
            <table border="0" cellpadding="0" cellspacing="0" style="margin: 30px auto 0 auto;">
                <tr>
                    <td align="center" bgcolor="#F2C94C" style="border-radius: 6px;">
                        <a href="' . $buttonUrl . '" target="_blank" style="font-family: \'Outfit\', Arial, sans-serif; font-size: 14px; font-weight: bold; color: #060608; text-decoration: none; padding: 15px 32px; display: inline-block; text-transform: uppercase; letter-spacing: 1.5px; border-radius: 6px;">
                            ' . $buttonText . '
                        </a>
                    </td>
                </tr>
            </table>';
        }

        // URL absoluta para a logo oficial
        $logoUrl = AppConfig::$APP_URL . '/assets/images/logo.png';

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $title . '</title>
        </head>
        <body style="background-color: #060608; color: #F5F5F7; font-family: \'Outfit\', Arial, sans-serif; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #0E0E12; border-top: 4px solid #F2C94C; border-left: 1px solid rgba(242, 201, 76, 0.08); border-right: 1px solid rgba(242, 201, 76, 0.08); border-bottom: 1px solid rgba(242, 201, 76, 0.08); margin-top: 40px; margin-bottom: 40px; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.65);">
                <!-- Header (Logo) -->
                <tr>
                    <td align="center" style="padding: 40px 30px 25px 30px; border-bottom: 1px solid rgba(242, 201, 76, 0.06);">
                        <a href="' . AppConfig::$APP_URL . '" target="_blank" style="text-decoration: none; display: inline-block;">
                            <img src="' . $logoUrl . '" alt="Logo GT Cursos" style="height: 52px; width: auto; max-width: 200px; display: block; border: 0; outline: none; object-fit: contain;">
                        </a>
                    </td>
                </tr>
                <!-- Body (Conteúdo) -->
                <tr>
                    <td style="padding: 40px 35px 35px 35px;">
                        <h1 style="font-family: \'Clash Display\', Arial, sans-serif; font-size: 22px; font-weight: bold; color: #F2C94C; margin-top: 0; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.5px;">
                            ' . $title . '
                        </h1>
                        <div style="font-size: 15px; line-height: 1.7; color: #EAEAEA; font-weight: 400;">
                            ' . $content . '
                        </div>
                        ' . $buttonHtml . '
                    </td>
                </tr>
                <!-- Footer (Rodapé) -->
                <tr>
                    <td align="center" style="padding: 30px; border-top: 1px solid rgba(242, 201, 76, 0.06); background-color: #09090C; font-size: 11px; color: #8F8F9D; line-height: 1.5; font-weight: 500;">
                        Este é um e-mail automático enviado pela plataforma GT Cursos.<br>
                        © ' . date('Y') . ' <a href="' . AppConfig::$APP_URL . '" target="_blank" style="color: #F2C94C; text-decoration: none; font-weight: bold;">GT Cursos</a>. Todos os direitos reservados.<br>
                        Plataforma de Alta Capacidade e Treinamentos de Elite.
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
}
?>
