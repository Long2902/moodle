<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Phiếu học tập & chấm điểm nhóm';
$string['modulename'] = 'Phiếu học tập & chấm điểm nhóm';
$string['modulenameplural'] = 'Phiếu học tập & chấm điểm nhóm';
$string['pluginadministration'] = 'Quản trị phiếu học tập';
$string['worksheetgrader:addinstance'] = 'Thêm hoạt động phiếu học tập';
$string['worksheetgrader:view'] = 'Xem hoạt động phiếu học tập';
$string['worksheetgrader:submit'] = 'Làm và nộp bài thay mặt nhóm';
$string['worksheetgrader:manageactivity'] = 'Quản lý nội dung phiếu';
$string['worksheetgrader:managesessions'] = 'Quản lý buổi/lượt hoạt động';
$string['worksheetgrader:manageteams'] = 'Quản lý nhóm tạm';
$string['worksheetgrader:grade'] = 'Chấm bài nhóm';
$string['worksheetgrader:publishgrades'] = 'Công bố điểm vào sổ điểm';
$string['worksheetgrader:viewreports'] = 'Xem báo cáo chấm điểm';
$string['worksheetgrader:export'] = 'Xuất báo cáo';
$string['engineurl'] = 'URL Worksheet Engine';
$string['engineurl_desc'] = 'Địa chỉ gốc của WebApp Python, ví dụ https://engine.example.edu.';
$string['enginesecret'] = 'Khóa bí mật dùng chung';
$string['enginesecret_desc'] = 'Chuỗi ngẫu nhiên dài, giống WORKSHEET_ENGINE_SHARED_SECRET trên máy chủ Python.';
$string['enginetimeout'] = 'Thời gian chờ engine';
$string['enginetimeout_desc'] = 'Thời gian tối đa chờ chuyển đổi DOCX/PDF.';
$string['allowtemporaryteams'] = 'Cho phép nhóm tạm theo từng buổi';
$string['allowtemporaryteams_desc'] = 'Giáo viên chia nhóm mới cho mỗi buổi mà không thay đổi Groups chính thức của khóa học.';
$string['name'] = 'Tên hoạt động';
$string['contenthtml'] = 'Nội dung phiếu mặc định';
$string['contenthtml_help'] = 'Nội dung này được sao chép vào buổi mới. Các mã như [[C1]] sẽ thành ô nhập liệu cho học sinh.';
$string['grade'] = 'Điểm tối đa';
$string['aggregation'] = 'Cách tổng hợp điểm giữa các buổi';
$string['aggregation_average'] = 'Trung bình';
$string['aggregation_best'] = 'Lấy buổi cao nhất';
$string['aggregation_latest'] = 'Lấy buổi được chấm gần nhất';
$string['aggregation_sum'] = 'Cộng, giới hạn ở điểm tối đa hoạt động';
$string['allowgroup'] = 'Cho phép bài làm nhóm';
$string['allowadjust'] = 'Cho phép điều chỉnh điểm từng thành viên';
$string['representative'] = 'Người được sửa bài làm chung';
$string['representative_anymember'] = 'Bất kỳ thành viên hiện tại';
$string['representative_fixed'] = 'Chỉ học sinh đại diện được chọn';
$string['completiononsubmit'] = 'Hoàn thành cho cả nhóm khi nộp bài';
$string['completionongrade'] = 'Hoàn thành cho cả nhóm khi công bố điểm';
$string['sessions'] = 'Buổi/Lượt hoạt động';
$string['teams'] = 'Nhóm & thành viên';
$string['grading'] = 'Chấm điểm';
$string['reports'] = 'Báo cáo';
$string['importworksheet'] = 'Nhập phiếu học tập';
$string['studentwork'] = 'Bài làm nhóm';
$string['privacy:metadata:wsg_attempt'] = 'Lưu câu trả lời chung và lịch sử nộp bài của nhóm.';
$string['privacy:metadata:wsg_attempt:submitteruserid'] = 'Tài khoản đã nộp thay mặt nhóm.';
$string['privacy:metadata:wsg_attempt:answersjson'] = 'Câu trả lời do nhóm nhập.';
$string['privacy:metadata:wsg_grade'] = 'Lưu điểm nhóm và điều chỉnh cá nhân.';
$string['privacy:metadata:wsg_grade:userid'] = 'Học sinh nhận điểm.';
$string['privacy:metadata:wsg_grade:finalgrade'] = 'Điểm cuối cùng đã công bố.';
$string['privacy:metadata:wsg_log'] = 'Lưu nhật ký thao tác của giáo viên và học sinh.';
$string['privacy:metadata:wsg_log:actorid'] = 'Người thực hiện thao tác.';

$string['cannotopenwithoutcontent'] = 'Không thể mở buổi khi chưa có nội dung phiếu.';
$string['cannotopenwithoutteams'] = 'Không thể mở buổi khi chưa có nhóm.';
$string['teamslocked'] = 'Danh sách nhóm đã khóa.';
$string['duplicateuserteam'] = 'Một học sinh xuất hiện trong nhiều nhóm.';
$string['cannotreplaceteamswithattempts'] = 'Không thể thay toàn bộ nhóm vì đã có bài làm.';
$string['attemptnoteditable'] = 'Bài làm không còn ở trạng thái cho phép sửa.';
$string['attemptconflict'] = 'Bài đã được sửa ở thiết bị khác. Hãy tải lại trang.';
$string['attemptalreadysubmitted'] = 'Bài đã được nộp.';
$string['notassignedteam'] = 'Bạn chưa được xếp vào nhóm của buổi này.';
$string['enginenotconfigured'] = 'Worksheet Engine chưa được cấu hình trong Site administration.';
$string['enginehttp'] = 'Worksheet Engine trả lỗi HTTP {$a}.';
$string['engineinvalidresponse'] = 'Worksheet Engine trả dữ liệu không hợp lệ.';
$string['overview'] = 'Tổng quan';
$string['worksheetcontent'] = 'Nội dung phiếu';
$string['nosessionyet'] = 'Chưa có buổi/lượt hoạt động. Hãy tạo buổi trước khi nhập nội dung hoặc chia nhóm.';
$string['content_save_failed'] = 'Không lưu được nội dung: {$a}';
$string['content_import_failed'] = 'Không chuyển đổi được file: {$a}';
$string['cannoteditclosedteams'] = 'Không thể thay đổi nhóm của một buổi đã đóng.';
$string['invalidteamid'] = 'Nhóm không thuộc buổi đang thao tác.';
$string['cannotmovesubmittedteam'] = 'Không thể thay đổi thành viên của nhóm đã nộp hoặc đã chấm. Bài cũ phải giữ nguyên snapshot thành viên.';
$string['cannotdeleteteamwithattempt'] = 'Không thể xóa nhóm đã có bài làm. Hãy giữ nhóm đó hoặc xử lý bài làm trước.';
$string['membershipbeingedited'] = 'Giáo viên đang điều chỉnh nhóm. Bài tạm thời không thể sửa hoặc nộp.';
$string['randomonlydraft'] = 'Chia ngẫu nhiên, nạp Groups Moodle và sao chép nhóm chỉ dùng trước khi mở buổi.';
$string['imageuploadfailed'] = 'Không tải được ảnh cho trường {$a}.';
$string['imageuploadinvalid'] = 'Ảnh của trường {$a} không hợp lệ hoặc vượt quá 10 MB.';
$string['imageuploadtype'] = 'Định dạng ảnh không được hỗ trợ: {$a}. Chỉ nhận JPG, PNG, WEBP và GIF.';

$string['attemptsavefailed'] = 'Không thể lưu hoặc nộp bài: {$a}';

$string['attemptlocktimeout'] = 'Bài đang được lưu ở một yêu cầu khác. Vui lòng thử lại sau vài giây.';

$string['workmode'] = 'Hình thức làm bài';
$string['workmode_help'] = 'Chọn Có chia nhóm cho bài làm chung, hoặc Không chia nhóm để mỗi học sinh có một bài cá nhân. Chế độ cá nhân tạo hồ sơ khi học sinh bắt đầu, không tạo hàng loạt nhóm rỗng.';
$string['invalidindividualsession'] = 'Buổi này không phải chế độ làm bài cá nhân.';
$string['randomwithattempts'] = 'Không thể chia lại toàn bộ ngẫu nhiên sau khi buổi đã có bài làm.';
$string['csvinvalidfile'] = 'File CSV trống, không đọc được hoặc vượt quá 2 MB.';
$string['csvnotindividual'] = 'Buổi cá nhân không sử dụng import/export CSV chia nhóm.';
$string['csvmissingteam'] = 'CSV phải có cột team_name.';
$string['csvrowerror'] = '{$a}';
$string['csvempty'] = 'CSV không chứa nhóm hợp lệ.';

// ONLYOFFICE DocSpace Cloud integration.
$string['docspaceheading'] = 'ONLYOFFICE DocSpace Cloud';
$string['docspaceheading_desc'] = 'Nhúng tài liệu DocSpace vào Moodle. Có thể dùng Shared Folder + requestToken để tiết kiệm room hoặc Isolated Rooms để cách ly chặt hơn.';
$string['docspaceenabled'] = 'Bật DocSpace Cloud';
$string['docspaceenabled_desc'] = 'Cho phép giáo viên chọn phiếu DocSpace bên cạnh phiếu HTML.';
$string['docspaceurl'] = 'URL cổng DocSpace';
$string['docspaceurl_desc'] = 'Ví dụ https://ten-truong.onlyoffice.com. Bắt buộc HTTPS.';
$string['docspaceapikey'] = 'API key DocSpace';
$string['docspaceapikey_desc'] = 'API key có quyền đọc/ghi Rooms và Files. Khóa này không được gửi tới trình duyệt.';
$string['docspacesdkversion'] = 'Phiên bản Embed SDK';
$string['docspacesdkversion_desc'] = 'Nên để auto. Plugin ưu tiên SDK 2.2.0 hiện tại và fallback về 2.0.0 cho tenant cũ.';
$string['docspacetimeout'] = 'Thời gian chờ DocSpace API';
$string['docspacetimeout_desc'] = 'Thời gian tối đa cho một yêu cầu API phía máy chủ.';
$string['docspacetest'] = 'Kiểm tra kết nối';
$string['docspacetestlink'] = 'Mở trang kiểm tra DocSpace';
$string['docspacenotconfigured'] = 'DocSpace Cloud chưa được cấu hình hoặc chưa được bật.';
$string['docspacehttpsrequired'] = 'URL DocSpace phải sử dụng HTTPS.';
$string['docspacehttp'] = 'DocSpace trả lỗi HTTP {$a}.';
$string['docspacehttpdetail'] = 'DocSpace trả lỗi HTTP {$a->code}: {$a->message}';
$string['docspaceinvalidresponse'] = 'DocSpace trả dữ liệu không hợp lệ hoặc thiếu {$a}.';
$string['docspacetemplatemissing'] = 'Buổi này chưa có tài liệu mẫu DocSpace.';
$string['docspacetemplatehasattempts'] = 'Không thể thay tài liệu mẫu DocSpace sau khi buổi đã có bài làm.';
$string['docspaceoperation'] = 'Cách tạo tài liệu mẫu';
$string['docspaceuploadtemplate'] = 'Tải tài liệu Office/PDF lên';
$string['docspaceblanktemplate'] = 'Tạo tài liệu trống trong DocSpace';
$string['docspacefile'] = 'Tệp mẫu';
$string['docspacefilename'] = 'Tên tài liệu trống';
$string['docspaceconfirmreplace'] = 'Tôi hiểu thao tác này sẽ thay tài liệu mẫu DocSpace hiện tại của buổi.';
$string['docspacecreatetemplate'] = 'Tạo tài liệu mẫu & mở ONLYOFFICE';
$string['docspacecontentmode'] = 'Loại nội dung phiếu';
$string['docspacecontentmode_html'] = 'Phiếu HTML tương tác';
$string['docspacecontentmode_docspace'] = 'Tài liệu ONLYOFFICE DocSpace';
$string['docspaceembedfailed'] = 'Không tải được ONLYOFFICE trong trang Moodle.';
$string['docspaceopenfallback'] = 'Mở tài liệu trong khung dự phòng';
$string['docspacesubmitwarning'] = 'Hãy đợi ONLYOFFICE lưu xong thay đổi trước khi nộp. Hệ thống sẽ chụp một bản DOCX/PDF vào Moodle.';
$string['docspacesnapshotmissing'] = 'Chưa có bản chụp tài liệu trong Moodle.';
$string['docspacesnapshotsaved'] = 'Đã lưu bản chụp tài liệu vào Moodle.';
$string['docspacepublicwarning'] = 'Chế độ Isolated Rooms dùng một Public Room riêng cho mỗi mẫu phiếu/bài làm. Cách này cách ly tốt hơn nhưng tiêu tốn quota room.';
$string['docspacetestsuccess'] = 'Kết nối DocSpace thành công.';
$string['docspacetestfailed'] = 'Kết nối DocSpace thất bại: {$a}';
$string['docspaceoriginhelp'] = 'Trong DocSpace → Developer Tools → Embed SDK, hãy thêm chính xác origin Moodle của bạn.';

$string['docspaceexistingattemptswitch'] = 'Buổi này đã có bài làm nên không thể đổi trực tiếp sang DocSpace. Hãy tạo một bản sao ONLYOFFICE để giữ nguyên lịch sử bài nộp.';
$string['docspaceclonebutton'] = 'Tạo bản sao buổi dùng ONLYOFFICE';
$string['docspaceautocreated'] = 'Đã tạo tài liệu DocSpace và mở ONLYOFFICE Editor đầy đủ.';

$string['docspaceworkspacemode'] = 'Kiểu không gian DocSpace';
$string['docspaceworkspacemode_desc'] = 'Shared Folder dùng một Folder ID + requestToken của Public Room để mở từng file bằng Editor, tiết kiệm room. Isolated Rooms tạo room riêng cho từng mẫu/bài để cách ly chặt hơn.';
$string['docspaceworkspacemode_shared'] = 'Shared Public Room Folder — tiết kiệm room';
$string['docspaceworkspacemode_isolated'] = 'Isolated Public Rooms — room riêng từng mẫu/bài';
$string['docspacefolderid'] = 'Folder ID DocSpace dùng chung';
$string['docspacefolderid_desc'] = 'ID thư mục nằm bên trong Public Room có quyền Editing. Plugin tải/copy file vào đây bằng API key.';
$string['docspacerequesttoken'] = 'Public Room requestToken dùng chung';
$string['docspacerequesttoken_desc'] = 'requestToken của Public Room chứa Folder ID. Token này được gửi xuống trình duyệt để initEditor(fileId) mà không yêu cầu tài khoản DocSpace. Không dùng API key ở đây.';
$string['docspacesharedwarning'] = 'Chế độ Shared Folder tiết kiệm room nhưng requestToken có quyền trên toàn Public Room. Chỉ dùng khi bạn chấp nhận người dùng kỹ thuật cao có thể trích token từ trình duyệt; Moodle chỉ hiển thị file đúng của họ.';

$string['noticeheading'] = 'Giao diện & thông báo';
$string['noticeheading_desc'] = 'Điều khiển các thông báo hướng dẫn và trạng thái kỹ thuật trên giao diện giáo viên/học sinh.';
$string['showguidancenotices'] = 'Hiển thị thông báo hướng dẫn';
$string['showguidancenotices_desc'] = 'Bật các hộp hướng dẫn/cảnh báo mang tính giải thích (ví dụ cảnh báo Shared Folder, hướng dẫn quy trình). Mặc định tắt để giao diện gọn hơn.';
$string['showdocspacestatus'] = 'Hiển thị trạng thái kỹ thuật ONLYOFFICE/DocSpace';
$string['showdocspacestatus_desc'] = 'Hiển thị thanh trạng thái tải SDK, xác thực và chế độ Editor/Viewer phía trên khung ONLYOFFICE. Mặc định tắt.';
$string['showerrornotices'] = 'Hiển thị thông báo lỗi bổ sung';
$string['showerrornotices_desc'] = 'Hiển thị các cảnh báo lỗi runtime bổ sung của ONLYOFFICE/DocSpace. Các lỗi bắt buộc để hoàn thành thao tác vẫn luôn được Moodle hiển thị để tránh lỗi im lặng.';

// Nền tảng phiếu học tập V12.
$string['v12heading'] = 'Nền tảng phiếu học tập V12';
$string['v12heading_desc'] = 'Luồng V12 triển khai bổ sung. Giữ tắt cho tới khi ONLYOFFICE và khóa học pilot vượt qua các cổng kiểm thử.';
$string['v12enabled'] = 'Bật luồng V12';
$string['v12enabled_desc'] = 'Khi bật, các khóa học được chọn dùng luồng Tạo buổi → Chọn phiếu → Tạo nhóm → Kiểm tra & mở.';
$string['v12pilotcourseids'] = 'ID khóa học pilot V12';
$string['v12pilotcourseids_desc'] = 'Danh sách ID khóa học, phân cách bằng dấu phẩy. Để trống nghĩa là áp dụng toàn bộ sau khi bật công tắc V12.';
$string['sessiondescription'] = 'Ghi chú của giáo viên';
$string['sessiondescription_help'] = 'Ghi chú tùy chọn hiển thị trong màn hình thiết lập buổi.';
$string['workmodeindividual'] = 'Cá nhân';
$string['workmodegrouped'] = 'Theo nhóm';
$string['chooseworksheet'] = 'Chọn phiếu';
$string['teamstudio'] = 'Tạo nhóm';
$string['readyopen'] = 'Xác nhận & mở buổi';
$string['readiness'] = 'Mức độ sẵn sàng';
$string['officesaveprogress'] = 'Lưu tiến độ';
$string['officesubmit'] = 'Nộp bài';
$string['officeworking'] = 'Tài liệu làm việc chung';
$string['officesaving'] = 'Đang lưu bản mới nhất…';
$string['officesaved'] = 'Đã lưu';
$string['officesavefailed'] = 'Không lưu được bản Office mới nhất.';

$string['backuprestoreverified'] = 'Đã xác nhận Backup/Restore & Course Publisher';
$string['backuprestoreverified_desc'] = 'CHỈ bật sau khi single-activity backup/restore sang khóa học khác và Course Publisher smoke test đã PASS trên staging/production disposable target. Mặc định tắt để fail-closed.';
