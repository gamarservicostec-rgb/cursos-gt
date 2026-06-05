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
                    <td align="center" bgcolor="#F2C94C" style="border-radius: 8px;">
                        <a href="' . $buttonUrl . '" target="_blank" style="font-family: Arial, sans-serif; font-size: 14px; font-weight: bold; color: #0A0A0C; text-decoration: none; padding: 14px 30px; display: inline-block; text-transform: uppercase; letter-spacing: 1px;">
                            ' . $buttonText . '
                        </a>
                    </td>
                </tr>
            </table>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . $title . '</title>
        </head>
        <body style="background-color: #0A0A0C; color: #F5F5F7; font-family: Arial, sans-serif; margin: 0; padding: 0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #141417; border: 1px solid rgba(255,255,255,0.05); margin-top: 40px; margin-bottom: 40px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                <!-- Header -->
                <tr>
                    <td align="center" style="padding: 40px 0 20px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size: 24px; font-weight: bold; letter-spacing: 2px; color: #F5F5F7;">
                            CURSOS <span style="color: #F2C94C;">GT</span>
                        </span>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style="padding: 40px 30px;">
                        <h1 style="font-size: 20px; font-weight: bold; color: #F2C94C; margin-top: 0; text-transform: uppercase;">' . $title . '</h1>
                        <div style="font-size: 15px; line-height: 1.6; color: #EAEAEA;">
                            ' . $content . '
                        </div>
                        ' . $buttonHtml . '
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td align="center" style="padding: 30px; border-top: 1px solid rgba(255,255,255,0.05); font-size: 11px; color: #8F8F9D; line-height: 1.4;">
                        Este é um e-mail automático enviado pela plataforma GT Cursos.<br>
                        © ' . date('Y') . ' Cursos GT - Todos os direitos reservados.<br>
                        Hospedagem de Alta Definição e Segurança de Elite.
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }
}
?>
