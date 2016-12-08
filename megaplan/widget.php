<?php
/**
 * @author Volkov Alexander
 */

$widget_id = filter_input(INPUT_GET, 'widget_id');

$result = null;

switch ($widget_id) {
    case 'new-call':
        $result = '
        <style>
            div.telephony-call-block { 
                position: absolute;
                right: 10px;
                bottom: 10px;
                padding: 20px 30px;
                background-color: white;
                box-shadow: 0 0 40px 0 rgba(0,0,0,.2), 0 0 15px 0 rgba(0,0,0,.3);
                z-index: 1000;
                border-radius: 5px;
            }
        </style>
        <div class="telephony-call-block" id="new-call">
            <div class="telephony-call-block__header">
                <h3>Звонит клиент <a href="/crm/1000000/card/" target="_blank">Синицын Олег Геннадьевич</a></h3><hr>
            </div>
            <div class="telephony-call-block__body">
                <b>Количество заказов: </b> 123<br><b>Последний звонок: </b> 23.11.2016 11:00<hr>
           </div>
            <div class="telephony-call-block__footer">
                <button>Новый заказ</button><button onclick="closeNotification(new-call)">Закрыть</button>
            </div>
        </div>';
        break;
    default:
        break;
}

return $result;