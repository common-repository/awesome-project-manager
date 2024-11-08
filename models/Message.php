<?php 


/**
* 
*/
class FusionPM_Message {

    protected $table_name;
    
    function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'fpm_messages';
    }

    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new FusionPM_Message();
        }

        return $instance;
    }

    public function get_formatted_date( $date ) {
        $date_format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );

        $datetime_format = $date_format.' '.$time_format;

        $given_date = date_create( $date );
        $formatted_date = date_format( $given_date, $datetime_format );

        return $formatted_date;
    }

    public function create( $data ) {
        global $wpdb;

        $insert = $wpdb->insert( $this->table_name, $data );

        if ( $insert ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    public function update( $data, $where ) {
        global $wpdb;

        $update = $wpdb->update( $this->table_name, $data, $where );

        if ( $update ) {
            return true;
        }

        return false;
    }

    public function delete( $message_id ) {
        global $wpdb;

        $commentModel = FusionPM_Comment::init();

        $commentModel->delete_comments( $message_id, 'message' );

        $result = $wpdb->delete( 
            $this->table_name,
            array( 'ID' => $message_id )
        );
        return $result;
    }

    public function delete_messages_by_column( $value, $column ) {

        global $wpdb;

        $result = $wpdb->get_results( "SELECT `ID` FROM {$this->table_name} WHERE {$column} = {$value}" );

        if ( !$result ) {
            return $result;
        }
         
        foreach ($result as $message) {
            $isSuccess = $this->delete($message->ID);
        }

        return $isSuccess;
    }

    public function get_message_count( $project_id ) {
        global $wpdb;

        $message_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE `projectID` = {$project_id}" );

        return $message_count;
    }

    public function get_message_details( $projectid, $messageid ) {
        global $wpdb;

        $files_array = array();
        $result = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE `ID` = {$messageid} AND `projectID` = {$projectid}"
        );
        if ( !$result ) {
            return $result;
        }
        $result[0]->formatted_created = $this->get_formatted_date( $result[0]->created );
        $result[0]->avatar_url = get_avatar_url( $result[0]->userID );

        $files = $result[0]->file_ids;
        $fileIDs = maybe_unserialize( $files );

        if ( $fileIDs ) {
            foreach ( $fileIDs as $ID ) {
                $fileObject = new stdClass();
                $fileObject->ID = $ID;
                $fileObject->url = wp_get_attachment_url( $ID );

                $filetype = wp_check_filetype( $fileObject->url );

                $fileObject->mime = $filetype['type'];
                $fileObject->extension = $filetype['ext'];
                $fileObject->icon = wp_mime_type_icon( $filetype['type'] );
                $fileObject->title = get_the_title($ID);
                // $fileObject->attachment_meta = wp_get_attachment_metadata( $ID );
                array_push( $files_array, $fileObject );
            }
            $result[0]->files = $files_array;
            $result[0]->attachmentIDs = $fileIDs;
        } else {
            $result[0]->files = [];
            $result[0]->attachmentIDs = [];
        }
        
        $commentModel = FusionPM_Comment::init();
        $messageComments = $commentModel->get_comments_by_column_info( $messageid, 'message' );

        $result[0]->comments = $messageComments;

        return $result;
    }

    public function get_messages_by_column_info( $column, $param, $limit = NULL, $offset = NULL ) {

        global $wpdb;

        if ( !$limit ) {
            $limit = 15;
        }

        if ( !$offset ) {
            $offset = 0;
        }

        $result = $wpdb->get_results( 
            "SELECT * FROM {$this->table_name} WHERE {$column} = {$param} ORDER BY `ID` DESC LIMIT {$limit} OFFSET {$offset}"
        );
        if ( !$result ) {
            return false;
        }
        foreach ( $result as $messageObject ) {
            $messageObject->avatar_url = get_avatar_url($messageObject->userID, array('size'=>70));
            $messageObject->formatted_created = $this->get_formatted_date( $messageObject->created );
        }
        $result[0]->message_count = $this->get_message_count( $messageObject->projectID );

        return $result;
    }

}