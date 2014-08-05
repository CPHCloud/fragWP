<?php
/*
Plugin Name: fragWP
Description: Utility plugin for fragment caching
Author: @supertroels
Version: 1.0
*/


/**
* ************
* fragWP class
* ************
*
* This plugin provides a framework for fragment caching.
* It handles both functions and files as the source of the
* fragments which makes it useful for caching both data and
* template parts.
*
*/
class fragWP {
    
    function __construct(){

        add_filter('fragWP/cache_prefix', array($this, 'add_user_id_to_prefix'), 1, 1);
        add_filter('flush_rules_key', array($this, 'add_user_id_to_flush_rules_key'), 1, 1);

        /* Set the $this->did_flush_action */
        $this->did_flush_action = array();

        /* Save the flush rules in the object */
        if(!$this->flush_rules = get_transient(apply_filters('fragWP/flush_rules_key', 'fragwp_flush_rules')))
            $this->flush_rules = array();

        /* Iterate the rules to apply them and do cleanup */
        foreach($this->flush_rules as $key => $rules){
                
            /* Cleanup of unused and expired rules */
            if($rules['ttl'] < time()){
                unset($this->flush_rules[$key]);
                delete_transient($key);
                continue;
            }

            /*
            This applies the flush_fragments method
            to all the actions neccesary. The method
            determines which fragment keys to flush.
            */
            if($rules['actions']){
                foreach($rules['actions'] as $action){
                    if(!in_array($action, $this->did_flush_action)){
                        add_action($action, array($this, 'flush_fragments'));
                        $this->did_flush_action[] = $action;
                    }
                }
            }
        }

    }

    function add_user_id_to_flush_rules($key){
        if(is_user_logged_in())
            $key .= get_current_user_id();
        return $key;
    }

    function add_user_id_to_prefix($prefix){
        if(is_user_logged_in())
            $prefix = 'fragwp'.get_current_user_id();
        return $prefix;
    }

    function save_frag($key, $frag, $ttl){
        if(set_transient($key, $frag, $ttl)){
            /* Hook action on succesful fragment caching */
            do_action('fragWP/fragment_cached', $key);
        }
    }

    function get_frag($key){
        return get_transient($key);
    }

    /**
     * This is the main function that is used to retreive a cached function
     * or file.
     *
     * @param $key - the unique key identifying this fragment
     * @param $source - the source of the fragment (string or callable)
     * @param $ttl - number of seconds from now to store the fragment
     * @param $flush_on - an array of action hooks that trigger a flush
     *
     * @return $frag - the fragment
     **/
    function get($key, $source, $ttl = DAY_IN_SECONDS, $flush_on = array(), $ob = false){

        $key  = md5($key.$ttl.serialize($flush_on));
        $key  = apply_filters('fragWP/cache_prefix', 'fragwp_cache_').$key;

        $frag = $this->get_frag($key);

        if(empty($frag)){

            /* Determine type of source */
            $source_type = false;
            if(is_callable($source)){
                $source_type = 'callback';
            }
            elseif(is_string($source)){
                $source_type = 'file';
            }
            else{
                error_log('Source type used in fragWP::get() is not valid. It must be either the full path to a file or a valid callback');
                return false;
            }

            if($ob){
                $GLOBALS['fragwp_key']      = $key;
                $GLOBALS['fragwp_ttl']      = $ttl;
                $GLOBALS['fragwp_flush_on'] = $flush_on;
                error_log('fragWP :: Caching entire output as '.$key);
                ob_start($source);
            }
            else{

                /* Start the buffer */
                ob_start();

                /* Call or require the source */
                if($source_type == 'callback'){
                    add_action('fragWP/call_source', $source);
                    do_action('fragWP/call_source');
                }
                else{
                    require $source;
                }
                
                /* Save buffer output in $frag */
                $frag = ob_get_clean();

                /* And finally save the output to a transient with the key name */
                $this->save_frag($key, $frag, $ttl);

            }

            /* Make sure that the flush rules are setup */
            if(!is_array($flush_on))
                $flush_on = array();

            $flush_on[] = 'fragwp/flush';

            /* If there are any flush rules, we deal with them here */
            if($flush_on){
                
                /* Get the saved flush rules */
                $fr_key = apply_filters('fragWP/flush_rules_key', 'fragwp_flush_rules');
                if(!$flush_rules = get_transient($fr_key)){
                    /*
                    Instantiate a new array if there are no rules
                    or the call returns false */
                    $flush_rules = array();
                }

                /* Add this rule to the ruleset */
                $flush_rules[$key]   = array(
                    'actions'   => $flush_on,
                    'ttl'       => time()+((int)$ttl) // <- Timestamp indicating when the rule self-terminates
                    );

                /* And save the ruleset */
                set_transient($fr_key, $flush_rules);

            }

        }

        /* Woo, frag! */
        return $frag;

    }


    /**
     * This flushes the fragments for the keys
     * that have specifieds the action hook
     * in their fourth parameter.
     *
     * @return void
     **/
    function flush_fragments(){

        /* Retreive the current action hook name */
        $action = current_filter();
        
        foreach($this->flush_rules as $key => $rules){
            /*
            Check if this fragment key has asked to
            be flushed on this action hook */
            if(in_array($action, $rules['actions'])){
                /* Bingo. Flush the fragment for the key */
                if(delete_transient($key)){
                    /* Hook action for succesful fragment flushing */
                    do_action('fragWP/fragment_flushed', $key, $action);
                }
            }
        }

    }

}

/* Instantiate the fragWP object */
$fragWP = new fragWP();

function fragwp(){
    global $fragWP;
    return $fragWP;
}

/**
 * Short hand for $fragWP->get().
 * Takes all the parameters fragWP::get() does.
 *
 * @return void
 **/
function get_frag($key, $source, $ttl = DAY_IN_SECONDS, $flush_on = array()){
    global $fragWP;
    return $fragWP->get($key, $source, $ttl, $flush_on);
}

/**
 * Used to output the fragment returned from get_frag()
 *
 * @return void
 **/
function frag($key, $source, $ttl = DAY_IN_SECONDS, $flush_on = array()){
    echo get_frag($key, $source, $ttl, $flush_on);
}

function frag_ob($key, $source, $ttl = DAY_IN_SECONDS, $flush_on = array()){
    global $fragWP;
    return $fragWP->get($key, $source, $ttl, $flush_on, true);
}


?>