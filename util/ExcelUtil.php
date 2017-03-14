<?php
/**
 * Created by PhpStorm.
 * User: licong
 * Date: 10/14/15
 * Time: 1:59 PM
 */
namespace app\util;

use Ddeboer\DataImport\Reader\ExcelReader;
use Ddeboer\DataImport\Writer\ExcelWriter;
use SplFileObject;


class QExcelWriter extends ExcelWriter
{
    public function writeItem(array $data)
    {

        foreach ($data as $item)
        {
            $count = count($item);
            $values = array_values($item);
            for ($i = 0; $i < $count; $i++)
            {
                $this->excel->getActiveSheet()->setCellValueByColumnAndRow($i, $this->row, $values[$i]);

            }
            $this->row++;
        }

        return $this;
    }
    /**
     * 写入表头
     * @param array $data
     * @return $this
     */
    public function writeHeader(array $data)
    {

        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $this->excel->getActiveSheet()->getStyleByColumnAndRow($i, $this->row, $data[$i])->getFont()->setBold(true);
            $this->excel->getActiveSheet()->setCellValueByColumnAndRow($i, $this->row, $data[$i]);
        }
        $this->row++;
        return $this;
    }
}

class ExcelUtil {
    public function formatExcelData($the_file_path, $excel_template_filename, $excel_template_dict){
        $import_data = $this->readerExcel($the_file_path);
        // 检测是否为正确的模板
        $validate_result = $this->validateExcel($import_data, $excel_template_filename);
        if (!$validate_result) {
            return $validate_result;
        }

        $count_import_data = count($import_data);
        $template_dict_str = $excel_template_dict;
        $template_dict = explode(',', $template_dict_str);
        $process_data_list = [];
        for($i=1; $i<$count_import_data; $i++){
            $row_data = $import_data[$i];
            if(empty($row_data)){
                continue;
            }
            $process_data = [];
            foreach($row_data as $index=>$val){
                if(!empty($row_data[$index])){
                    $process_data[$template_dict[$index]] = $row_data[$index];
                }
            }
            if(!empty($process_data)){
                array_push($process_data_list, $process_data);
            }
        }
        return $process_data_list;
    }

    public function validateExcelData($process_data, $field_list){
        if(empty($process_data) || empty($field_list)){
            return ['success'=>false, 'msg'=>'数据错误,没有需要验证的数据', 'need_process_data'=>$process_data];
        }
        $error_data = [];
        $need_process_data = [];
        foreach($process_data as $index=>$item){
            $is_error = false;
            foreach($field_list as $field){
                if(!array_key_exists($field, $item) || empty($item[$field])){
                    $error_data[(string)$index] = $field;
                    $is_error = true;
                    break;
                }
            }
            if(!$is_error){
                $need_process_data[(string)$index] = $item;
            }
        }
        if(empty($need_process_data)){
            return ['success'=>false, 'msg'=>'所有导入数据均不完整', 'error_data'=>$error_data];
        }
        return ['success'=>true, 'need_process_data'=>$need_process_data, 'error_data'=>$error_data];
    }

    public function readerExcel($file_path){
//        $path = str_replace('\\','/',realpath(dirname(__FILE__).'/'))."/";
//        $file_path = $path.'/public/excel/'.$file_name;
        $file = new SplFileObject($file_path);
        $reader = new E($file);

        $temp = [];
        //输出数据
        while (@$reader->current()) {
            $data = $reader->current();
            // 避免空行数据
            $temp_row = implode('', $data);
            if (!empty($temp_row)) {
                array_push($temp, $data);
            };
            $reader->next();
        }
        return $temp;
    }

    public function writerExcel($file_name, $data, $header = null, $disk_path = true)
    {
        $path = $_SERVER['DOCUMENT_ROOT'];
        $return_path = $path . '/excel/' . $file_name . '.xlsx';
        $file = new SplFileObject($return_path, 'w');
        $writer = new QExcelWriter($file, null);
        $writer->prepare();
        if ($header) {
            $writer->writeHeader($header)->finish();
        }
        $writer->writeItem($data)->finish();
        if($disk_path){
            return $return_path;
        }else{
            return '/excel/' . $file_name . '.xlsx';
        }


    }

    function getBasename($filename)
    {
        return preg_replace('/^.+[\\\\\\/]/', '', $filename);
    }

    public function downloadExcelTemplate($file_name)
    {
//        $path = str_replace('\\','/',realpath(dirname(__FILE__).'/'))."/";
        $path = $_SERVER['DOCUMENT_ROOT'];
        $file_path = $path . '/template/' . $file_name;
        $this->downloadFile($file_path);
    }

    public function downloadFile($file_path)
    {
        if (!file_exists($file_path)) {
            echo "文件不存在";
            return;
        }
        //获取下载文件的大小
        $file_size = filesize($file_path);
        $fp = fopen($file_path, "r");

        $file_name = $this->getBasename($file_path);

        // 清空缓冲区，防止乱码
        ob_end_clean();

        //返回的文件
        header("Content-type:application/octet-stream");
        //按照字节大小返回
        header("Accept-Ranges:bytes");
        //返回文件大小
        header("Accept-Length:$file_size");
        //这里客户端的弹出对话框
        header("Content-Disposition:attachment;filename=" . $file_name);
        //向客户端回送数据
        $buffer = 1024;
        //为了下载的安全。我们最后做一个文件字节读取计数器
        $file_count = 0;
        //判断文件是否结束
        while (!feof($fp) && ($file_size - $file_count > 0)) {
            $file_data = fread($fp, $buffer);
            //统计读了多少个字节
            $file_count += $buffer;
            //把部分数据回送给浏览器
            echo $file_data;
        }
        fclose($fp);
    }

    public function validateExcel($import_data, $template_type)
    {
        if (empty($import_data) || count($import_data) <= 1) {
            return ['success' => false, 'msg' => '空文件或模板错误'];
        }

        $excel_util = new ExcelUtil();
        $path = $_SERVER['DOCUMENT_ROOT'];
        $template_file_name = $path . ConstantConfig::EXCEL_TEMPLATE_PATH . '/' . $template_type;
        $template_data = $excel_util->readerExcel($template_file_name);

        if (empty($template_data)) {
            return ['success' => false, 'msg' => '原始模板文件为空'];
        }

        foreach ($template_data[0] as $index => $temp_val) {
            try {
                $c_data = $import_data[0][$index];
                if ($temp_val != $c_data) {
                    return ['success' => false, 'msg' => '上传文件与模板文件属性列不一致'];
                }
            } catch (\Exception $e) {
                return ['success' => false, 'msg' => '上传文件与模板文件属性列不一致'];
            }

        }
        return ['success' => true, 'msg' => '上传文件与模板文件属性一致'];
    }
}
