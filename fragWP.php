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

        /* Set the $this->did_flush_action */
        $this->did_flush_action = array();

        /* Save the flush rules in the object */
        if(!$this->flush_rules = get_transient('fragwp_flush_rules'))
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
            foreach($rules['actions'] as $action){
                if(!in_array($action, $this->did_flush_action)){
                    add_action($action, array($this, 'flush_fragments'));
                    $this->did_flush_action[] = $action;
                }
            }
        }

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
    function get($key, $source, $ttl = DAY_IN_SECONDS, $flush_on = array()){


        $key  = md5(serialize($source).$key.$ttl.serialize($flush_on));
        $key  = apply_filters('fragWP/cache_prefix', 'fragwp_cache_').$key;

        $frag = get_transient($key);

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
            if(set_transient($key, $frag, $ttl)){
                /* Hook action on succesful fragment caching */
                do_action('fragWP/fragment_cached', $key);
            }

            /* If there are any flush rules, we deal with them here */
            if($flush_on){
                
                /* Get the saved flush rules */
                if(!$flush_rules = get_transient('fragwp_flush_rules')){
                    /*
                    Instantiate a new array if there are no rules
                    or the call returns false */
                    $flush_rules = array();
                }

                /* Add this rule to the ruleset */
                $flush_rules[$key]   = array(
                    'actions'   => $flush_on,
                    'ttl'       => time()+$ttl // <- Timestamp indicating when the rule self-terminates
                    );

                /* And save the ruleset */
                set_transient('fragwp_flush_rules', $flush_rules);

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


/**
 * Short hand for $fragWP->get().
 * Takes all the parameters fragWP::get() does.
 *
 * @return void
 **/
function get_frag($key, $source, $flush_on = array(), $ttl = DAY_IN_SECONDS){
    global $fragWP;
    return $fragWP->get($key, $source, $flush_on, $ttl);
}

/**
 * Used to output the fragment form returned get_frag()
 *
 * @return void
 **/
function frag($key, $source, $flush_on = array(), $ttl = DAY_IN_SECONDS){
    echo get_frag($key, $source, $flush_on, $ttl);
}


?>