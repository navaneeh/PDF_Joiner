<?php

//require_once('tcpdf/config/lang/eng.php');
require_once('TCPDF/examples/tcpdf_include.php');
require_once('TCPDF/examples/lang/eng.php');

class Process
{
    public $connection='';
    public $main_dir='notes';
    public $ds = DIRECTORY_SEPARATOR;

    public function __construct()
    {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "academy";

        // Create connection
        $conn = new mysqli($servername, $username, $password, $dbname);
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $this->conection=$conn;

    }

    public function All_session_list()
    {
        ini_set('max_execution_time', 3000);
        ini_set("memory_limit","-1");
        $result =$this->conection->query($this->custom_query('final_file_creation'));//final_file_creation

        $master_folder='';
        $all_files_merge=[];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                
                $folder=$this->getFolderNamedetails($row);
                $notes_url=$row["notes_url"];
                $master_folder=$folder['master_folder'];
                $sub_folder=$folder['sub_folder'];
                $notes_pdf_dir=$this->main_dir. $this->ds .$master_folder. $this->ds .$sub_folder. $this->ds;

                echo "<br>Day: ". $row["day"]. ",<a href=" . $row["classroom_url"] . " target='_blank'>" . $row["session_name"] . "-->".implode(",",array_values($folder))."</a><br>";
                
                //folder creation
                $return0=$this->fileExistOrNotcheckAndCreate($master_folder,$sub_folder);

                //pdf filecreation
                    //Notes pdf creation
                    if($row["notes_type"]=='pdf')
                    {
                        $merge_file_name="merged.pdf";
                        $all_files_merge[]=$notes_pdf_dir.$merge_file_name;

                        $notes_file_name='notes.pdf';
                        $return1=$this->downloadPdffromUrl($notes_pdf_dir,$notes_file_name,$notes_url);
                    }

                    //title pdf creation
                    $title_file_name="title.pdf";
                    $title_dir=__dir__.$this->ds.$notes_pdf_dir;
                    $retun_file=$this->createTitlePdf($row,$title_dir,$title_file_name);

                     //merged pdf creation
                     $input_list=implode(" ",[$notes_pdf_dir.'title.pdf',$notes_pdf_dir.'notes.pdf']);
                     $retun_file=$this->mergefiles($notes_pdf_dir.$merge_file_name,$input_list);
                    
                     //count the total page
                     if($row['total_page']==0 && $row["notes_type"]=='pdf')
                     {
                        $page_count=$this->getThePageCount($notes_pdf_dir.$notes_file_name);
    
                        $return2=$this->conection->query('update all_classes set total_page='.$page_count.' where day='.$row["day"].'');
                     }
                //echo $notes_pdf_dir.$merge_file_name.'<br>';
                // exit();
            }

            if(!empty($master_folder))
            {
                // echo '<pre>';
                // var_Export($all_files_merge);
                // exit();
                $final_pdf=$master_folder.'.pdf';
                $output_dir=$this->main_dir.$this->ds.$master_folder.$this->ds.$final_pdf;
                $input_dir=implode(" ",$all_files_merge);
                $this->mergefiles($output_dir,$input_dir);
            }
            echo "Success";
        } else {
            echo "0 results";
        }
    }

    public function getFolderNamedetails($data)
    {
        $master_folder_name=$this->folderNameTrimfor($data["batch"]);

        $sub_folder_name='day-'.$data['day'].'--'.$this->folderNameTrimfor($data["session_name"]);
        $sub_folder_name=$this->folderNameTrimfor($sub_folder_name);
       
        return ["master_folder"=>$master_folder_name,"sub_folder"=>$sub_folder_name];
    }

    public function folderNameTrimfor($name)
    {
        return str_replace("&","and",str_replace(':','_',str_replace(' ','_',strtolower($name))));
    }

    public function fileExistOrNotcheckAndCreate($master_folder,$sub_folder)
    {
        $ds=$this->ds;
        if(!file_exists($this->main_dir. $ds . $master_folder))
        {
            mkdir($this->main_dir. $ds . $master_folder, 0777);
            chmod($this->main_dir. $ds . $master_folder, 0777);
        }

        if(!file_exists($this->main_dir. $ds . $master_folder . $ds .$sub_folder))
        {
            mkdir($this->main_dir. $ds . $master_folder . $ds .$sub_folder, 0777);
            chmod($this->main_dir. $ds . $master_folder . $ds .$sub_folder, 0777);
        }

        //exit();
    }

    public function custom_query($for)
    {
        if($for=='folder_creation')return 'SELECT * FROM all_classes WHERE notes_url IS NOT NULL AND classroom_url is not null and notes_type="pdf"';
        else if($for=='final_file_creation')return 'SELECT * FROM all_classes WHERE notes_url IS NOT NULL AND classroom_url is not null and notes_type="pdf" and batch="Advanced DSA" order by day asc';
        else if($for=='pending_file')return 'SELECT * FROM all_classes WHERE notes_url IS NOT NULL AND classroom_url is not null and notes_type="pdf" and DAY IN (37,50,7,16)';

    }

    public function downloadPdffromUrl($dest_dir,$file_name,$url)
    {
        if(!file_exists($dest_dir.$file_name))
        {
            file_put_contents($dest_dir.$file_name,file_get_contents($url));
        }
    }

    public function createTitlePdf($data,$notes_pdf_dir,$title_file_name)
    {
        $title=$this->createHeadingContent($data);
        $heading=$data['session_name'];

        if(!file_exists($notes_pdf_dir.$title_file_name) || true)
        {
            $return =$this->generatePdf($heading,$title,$notes_pdf_dir,$title_file_name);
        }
    }

    public function mergefiles($output_dir,$input_list)
    {   
        if(!file_exists($output_dir))
        {
            $cmd='gswin64.exe -dNOPAUSE -sDEVICE=pdfwrite -sOUTPUTFILE='.$output_dir.' -dBATCH '.$input_list.'';
            exec($cmd);
            //echo "CMD:::".$cmd."<br>";
        } 
    }

    public function createHeadingContent($data)
    {
        $add_on='';
        if($data['day']>1000)
        {
            $data['day']=0;
            $add_on='(optional)';
        }

        return 'Day - '.$data['day'].'::::::'.$this->getDate($data).$add_on;
    }

    public function getDate($data)
    {
        return strtoupper(str_replace(' ',' - ',$data['date'])).' - '.$data['year'];
    }
    public function getThePageCount($path)
    {
        $pdf = file_get_contents($path);
        $number = preg_match_all("/\/Page\W/", $pdf, $dummy);
        return $number;
    }

    public function prepareTheIndex($batch)
    {
        $result =$this->conection->query('select * from all_classes where batch="'.$batch.'" and notes_type="pdf" and notes_url IS NOT NULL AND classroom_url is not null  order by day asc');//final_file_creation
        
        $tr='';
            $page_no=1;
            $s_no=1;
            while($row = $result->fetch_assoc()) 
            {
                $add_on='';
                if($row['day']>1000)
                {
                    $row['day']='--';
                    $add_on='(optional)';
                }
                $tr.='<tr>';
                    $tr.='<td>'.$s_no.'</td>';
                    $tr.='<td>'.$row['day'].'</td>';
                    $tr.='<td>'.$this->getDate($row).'</td>';
                    $tr.='<td>'.ucwords(strtolower($row['session_name'])).$add_on.'</td>';
                    $tr.='<td>'.$page_no.'</td>';
                $tr.='</tr>';

                $page_no+=$row['total_page'];
                $page_no++;
                $s_no++;
            }
        
        
        $table='<table border=2>
                    <thead style="font-weight: 600;user-select: auto;font-size: 26px;">
                        <tr>
                            <th>S.No</th>
                            <th>Day</th>
                            <th>Date</th>
                            <th>Session Name</th>
                            <th>Page No</th>
                        </tr>
                    <thead>
                    <tbody>
                        '.$tr.'
                    </tbody>
                </table>
        ';
        $html='<html>
                <style>
                td {
                    text-align: center;
                    }
                </style>
                <body>
                    '.$table.'
                <body>
                </html>';

        echo $html;
    }

    public function generatePdf($heading='',$title='',$dir,$file_name)
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('Nicola Asuni');
        $pdf->setTitle('TCPDF Example 002');
        $pdf->setSubject('TCPDF Tutorial');
        $pdf->setKeywords('TCPDF, PDF, example, test, guide');

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // set default monospaced font
        $pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

        // set auto page breaks
        $pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set font
        $pdf->setFont('times', 'BI', 30);

        // add a page
        $pdf->AddPage();

        // set some text to print
        
        $txt = <<<EOD
        $heading



        $title
        EOD;

        // print a block of text using Write()
        $pdf->Write(0, $txt, '', 0, 'C', true, 0, false, false, 0);

        // ---------------------------------------------------------
        ob_clean();
       
         $pdf->Output($dir.$file_name, 'F');
    }

    public function publishPdfFile($master_folder)
    {
        ini_set('max_execution_time', 3000);
        ini_set("memory_limit","-1");

        $notes_pdf_dir=$this->main_dir. $this->ds .$master_folder. $this->ds;
        $input_list=implode(" ",[$notes_pdf_dir.'first_page.pdf',$notes_pdf_dir.'index.pdf',$notes_pdf_dir.$master_folder.'_page.pdf']);
        $retun_file=$this->mergefiles($notes_pdf_dir.$master_folder.'_original.pdf',$input_list);
    }

}

$data=new Process();
//$data->All_session_list();//common function
//$data->prepareTheIndex('Advanced DSA');//creating the index
$data->publishPdfFile('advanced_dsa');// mege the index,first_page,final_notes_with_page
?>