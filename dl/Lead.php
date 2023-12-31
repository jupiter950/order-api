<?php
/**
 * Lead.php
 * User: sami
 * Date: 24-Mar-15
 * Time: 12:05 PM
 */

namespace dl;


use Symfony\Component\HttpFoundation\Request;

class Lead extends DLO {

    const CODE_OFFSET = 50000;

    private $contact;

    function __construct(IDB $db, Contact $contact)
    {
        parent::__construct($db);
        $this->contact = $contact;
    }


    public function createLead(Request $request)
    {

        $reply = array(
            'success' => false,
            'err' => '',
            'data' => array(),
        );

        $data['name'] = $request->request->get('name', null);
        $data['company_name_he'] = $request->request->get('company_name_he', null);
        $data['company_name_en'] = $request->request->get('company_name_en', null);
        $data['phone'] = $request->request->get('phone', null);
        $data['ext'] = $request->request->get('ext', null);
        $data['mobile'] = $request->request->get('mobile', null);
        $data['fax'] = $request->request->get('fax', null);
        $data['mail'] = $request->request->get('mail', null);
        $data['address'] = $request->request->get('address', null);
        $data['remarks'] = $request->request->get('remarks', null);
        $data['otf'] = $request->request->get('otf', 0);
        $data['pc'] = $request->request->get('pc', 0);
        $data['mac'] = $request->request->get('mac', 0);
        $data['fonts'] = $request->request->get('fonts', array());

        $username = $request->request->get('username', array());
        $password = $request->request->get('password', array());

        if(false === ($id = $this->doCreateLead($data))) {
            $reply['success'] = false;
            $reply['err'] = 'Failed to create lead';
        } else {
            $reply['success'] = true;
            $reply['data']['leadId'] = $id;
            $reply['data']['leadCode'] = $id + self::CODE_OFFSET;
            $attachedTo = $this->attachLeadToContact($id, $username, $password);
            if(0 < $attachedTo) {
                $reply['data']['attachedTo'] = $attachedTo;
            }
        }

        return $reply;
    }

    public function doCreateLead($data)
    {
        $sql = '
          INSERT INTO `lead`
            (
            `name`,
            `company_name_he`,
            `company_name_en`,
            `creationDateTime`,
            `phone`,
            `ext`,
            `mobile`,
            `fax`,
            `mail`,
            `address`,
            `remarks`,
            `otf`,
            `pc`,
            `mac`,
            `state`)
            VALUES
            (
            :name,
            :company_name_he,
            :company_name_en,
            now(),
            :phone,
            :ext,
            :mobile,
            :fax,
            :mail,
            :address,
            :remarks,
            :otf,
            :pc,
            :mac,
            1
            );
        ';
        list($stmt, $db) = $this->db->getStatementAndDb($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':company_name_he', $data['company_name_he']);
        $stmt->bindParam(':company_name_en', $data['company_name_en']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':ext', $data['ext']);
        $stmt->bindParam(':mobile', $data['mobile']);
        $stmt->bindParam(':fax', $data['fax']);
        $stmt->bindParam(':mail', $data['mail']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':remarks', $data['remarks']);
        $stmt->bindParam(':otf', $data['otf']);
        $stmt->bindParam(':pc', $data['pc']);
        $stmt->bindParam(':mac', $data['mac']);
        
        if($stmt->execute()) {
            $id = $db->lastInsertId();
            $this->setLeadCode($id, (self::CODE_OFFSET + $id));
            $this->setLeadFonts($id, $data['fonts']);
            return $id;
        }
        return false;
    }

    private function setLeadCode($leadId, $leadCode)
    {
        return $this->db->execSql('UPDATE lead set leadcode=' . intval($leadCode, 10) . ' where id=' . intval($leadId, 10));
    }

    private function setLeadFonts($leadId, $fonts = array())
    {
        if(!is_array($fonts) || 0 == count($fonts) || !(0 < $leadId)) {
            return false;
        }

        $sql = 'INSERT INTO lead_font (lead_id, font_id) VALUES ';
        $values = array();
        for($i = 0, $len = count($fonts); $i < $len; $i++) {
            $fontId = intval($fonts[$i], 10);
            if(0 < $fontId) {
                $values []= '('.$leadId.', '.$fontId.')';
            }
        }
        if(!(0 < count($values))) {
            return false;
        }
        return $this->db->execSql($sql . implode(',', $values));
    }

    public function attachLeadToContact($leadId, $username, $password)
    {
        $contactId = $this->contact->getContactId($username, $password);
        if(!(0 < $contactId)) {
            return -1;
        }
        if($this->doAttachLeadToContact($leadId, $contactId)) {
            return $contactId;
        }
        return -2;
    }

    private function doAttachLeadToContact($leadId, $contactId)
    {
        $leadId = intval($leadId, 10);
        $contactId = intval($contactId, 10);
        if(!(0 < $leadId) || !(0 < $contactId)) {
            return false;
        }
        return (1 == $this->db->execSql(
                'update lead set contactId=' . $contactId . ' where id = ' . $leadId . ' and contactId is null;'
            ));
    }

    public function getLead($leadId)
    {
        $leadId = intval($leadId, 10);
        if(!(0 < $leadId)) {
            return -1;
        }

        $stmt = $this->db->getStatement("select * from lead where id=:id");
        if(!$stmt->execute(array(':id' => $leadId))) {
            return -2;
        }
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        $res['fonts'] = array();
        $fontsStmt = $this->db->getStatement("
            select
                f.id id, fg.name `group`, fw.name `weight`, concat(fg.name, ' ', fw.name) `name`
            from
                lead_font l
                inner join font f on(l.lead_id=:lead_id and l.font_id = f.id)
                inner join fontgroup fg on (f.fontGroupId = fg.id)
                inner join fontweight fw on (f.fontWeightId = fw.id)
            order by
                fg.name, fw.ord, fw.name
        ");
        if($fontsStmt->execute(array(':lead_id' => $leadId))) {
            while($row = $fontsStmt->fetch(\PDO::FETCH_ASSOC)) {
                $res['fonts'] []= $row;
            }
        }
        return $res;
    }

}