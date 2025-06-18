<?php
// includes/CodeValidator.php
// مسؤول عن التحقق من صحة بيانات الأكواد قبل التنفيذ

namespace WP_AI_Agent\Includes;

/**
 * استثناء فشل التحقق من البنية
 */
class ValidationException extends \Exception {
    protected $details = [];

    public function __construct( string $message, array $details = [] ) {
        parent::__construct( $message );
        $this->details = $details;
    }

    /** تفاصيل الأخطاء */
    public function getDetails(): array {
        return $this->details;
    }
}

class CodeValidator {

    /**
     * تفويض التحقق حسب نوع الكود
     *
     * @param string $type  json|php|js
     * @param mixed  $data  مصفوفة JSON مفكوكة أو نص الكود
     * @throws ValidationException
     */
    public function validate( string $type, $data ) {
        switch ( $type ) {
            case 'json':
                $this->validateJson( $data );
                break;
            case 'php':
                $this->validatePhp( $data );
                break;
            case 'js':
                $this->validateJs( $data );
                break;
            default:
                throw new ValidationException( "نوع الكود غير مدعوم: {$type}" );
        }
    }

    /**
     * تحقق من بنية JSON وأوامره
     */
    protected function validateJson( array $data ) {
        if ( empty( $data['command'] ) ) {
            throw new ValidationException( "حقل 'command' مفقود في JSON", [ 'field' => 'command' ] );
        }

        $allowed = [ 'create_page', 'update_option', 'delete_page' ];
        if ( ! in_array( $data['command'], $allowed, true ) ) {
            throw new ValidationException( "الأمر غير مدعوم: {$data['command']}", [ 'command' => $data['command'] ] );
        }

        // أمثلة تحقق حسب الأمر
        switch ( $data['command'] ) {
            case 'create_page':
                if ( empty( $data['title'] ) ) {
                    throw new ValidationException( "حقل 'title' مطلوب لإنشاء صفحة", [ 'field' => 'title' ] );
                }
                if ( empty( $data['slug'] ) ) {
                    throw new ValidationException( "حقل 'slug' مطلوب لإنشاء صفحة", [ 'field' => 'slug' ] );
                }
                break;

            case 'update_option':
                if ( empty( $data['option_name'] ) || ! isset( $data['option_value'] ) ) {
                    throw new ValidationException(
                        "حقل 'option_name' أو 'option_value' مفقود لتحديث الخيار",
                        [ 'fields' => [ 'option_name', 'option_value' ] ]
                    );
                }
                break;

            // إضافة تحقق لأوامر أخرى هنا...
        }
    }

    /**
     * منع دوال PHP الخطرة
     */
    protected function validatePhp( string $code ) {
        $blacklist = [ 'exec(', 'system(', 'passthru(', 'shell_exec(', 'eval(' ];
        foreach ( $blacklist as $fn ) {
            if ( stripos( $code, $fn ) !== false ) {
                throw new ValidationException( "دالة غير مسموح بها في PHP: {$fn}", [ 'function' => $fn ] );
            }
        }
    }

    /**
     * منع استدعاءات الشبكة في JS
     */
    protected function validateJs( string $code ) {
        if ( preg_match( '/fetch\(/i', $code ) ) {
            throw new ValidationException( "استخدام غير مصرح به لـ fetch في JS", [ 'pattern' => 'fetch' ] );
        }
    }
}
