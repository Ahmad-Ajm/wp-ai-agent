<?php
// includes/CodeExecutorFactory.php
// مسؤول عن إنشاء المعالج المناسب لكل نوع كود

namespace WP_AI_Agent\Includes;

/**
 * استثناء فشل التنفيذ
 */
class ExecutionException extends \Exception {}

class CodeExecutorFactory {

    /**
     * إنشاء المعالج الصحيح حسب النوع
     *
     * @param string $type json|php|js
     * @return mixed كائن المعالج
     * @throws ExecutionException
     */
    public static function create( string $type ) {
        switch ( $type ) {
            case 'json':
                return new JsonCodeExecutor();
            case 'php':
                return new PhpCodeExecutor();
            case 'js':
                return new JsCodeExecutor();
            default:
                throw new ExecutionException( "لا يوجد معالج للكود من نوع: {$type}" );
        }
    }
}

/** ===== معالجات الأكواد ===== **/

/**
 * تنفيذ أوامر JSON (مثال: إنشاء صفحة، تحديث خيار)
 */
class JsonCodeExecutor {

    /**
     * @param array $data مصفوفة مفكوكة من JSON
     * @return array النتيجة
     * @throws ExecutionException
     */
    public function execute( array $data ): array {
        switch ( $data['command'] ) {

            case 'create_page':
                $postarr = [
                    'post_title'   => sanitize_text_field( $data['title'] ),
                    'post_name'    => sanitize_title( $data['slug'] ),
                    'post_content' => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ];
                $id = wp_insert_post( $postarr, true );
                if ( is_wp_error( $id ) ) {
                    throw new ExecutionException( $id->get_error_message() );
                }
                return [ 'page_id' => $id ];

            case 'update_option':
                $ok = update_option(
                    sanitize_text_field( $data['option_name'] ),
                    wp_kses_post( $data['option_value'] )
                );
                if ( false === $ok ) {
                    throw new ExecutionException( "فشل تحديث الخيار: {$data['option_name']}" );
                }
                return [ 'updated' => true ];

            case 'delete_page':
                $deleted = wp_delete_post( intval( $data['page_id'] ), true );
                if ( ! $deleted ) {
                    throw new ExecutionException( "فشل حذف الصفحة: ID {$data['page_id']}" );
                }
                return [ 'deleted' => true ];

            default:
                throw new ExecutionException( "أمر JSON غير معروف: {$data['command']}" );
        }
    }
}

/**
 * تنفيذ كود PHP خام
 */
class PhpCodeExecutor {

    /**
     * @param string $code
     * @return array مخرجات التنفيذ
     * @throws ExecutionException
     */
    public function execute( string $code ): array {
        ob_start();
        try {
            // تنفيذ آمن قدر الإمكان
            eval( $code );
        } catch ( \Throwable $e ) {
            ob_end_clean();
            throw new ExecutionException( "خطأ في PHP: " . $e->getMessage() );
        }
        $output = ob_get_clean();
        return [ 'output' => $output ];
    }
}

/**
 * تنفيذ JS على السيرفر غير مدعوم
 */
class JsCodeExecutor {

    /**
     * @param string $code
     * @throws ExecutionException
     */
    public function execute( string $code ) {
        throw new ExecutionException( "تنفيذ JS على السيرفر غير مدعوم" );
    }
}
