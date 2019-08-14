<?php

class Janolaw_Agb_Model_Email_Template extends Janolaw_Agb_Model_Email_Template_Compatibility
{

    public function sendTransactional($templateId, $sender, $email, $name, $vars=array(), $storeId=null)
    {
        Mage::dispatchEvent(
            'janolaw_send_transactional_before',
            array(
                'template_id' => $templateId,
                'sender' => $sender,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'vars' => $vars,
                'store_id' => $storeId,
                'template_model' => $this,
            )
        );

        parent::sendTransactional($templateId, $sender, $email, $name, $vars, $storeId);

        Mage::dispatchEvent(
            'janolaw_send_transactional_after',
            array(
                'template_id' => $templateId,
                'sender' => $sender,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'vars' => $vars,
                'store_id' => $storeId,
                'template_model' => $this,
            )
        );
        return $this;
    }
} 