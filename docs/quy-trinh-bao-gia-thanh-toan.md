## 0. Quy trình nghiệp vụ đã thống nhất (bắt buộc tuân theo)

Việc đầu tiên: tạo file docs/quy-trinh-bao-gia-thanh-toan.md trong dự án, chép NGUYÊN VĂN nội dung mục 0 này vào đó (các bước sau của dự án sẽ đọc lại file này). Không sửa nội dung quy trình.

### 0.1. Quy tắc nghiệp vụ
- Một cơ hội có 0 hoặc nhiều báo giá; một báo giá có 0 hoặc 1 đơn hàng; một đơn hàng có 1 hoặc nhiều hóa đơn.
- Báo giá chỉ có 3 trạng thái: Draft (Nháp), Confirmed (Đã xác nhận), Rejected (Từ chối). KHÔNG có hủy hay xóa báo giá. Báo giá lập sai thì sửa khi còn Nháp, hoặc Từ chối.
- Báo giá không có hình thức hay phương thức thanh toán. Tổng tiền báo giá KHÔNG lưu trong CSDL, luôn tính ra: tổng line_total của các dòng chi tiết (đã gồm VAT) trừ giảm giá voucher (voucher phần trăm: làm tròn 2 số lẻ; voucher số tiền cố định: không vượt quá tạm tính). Dùng Voucher::discountFor().
- Báo giá chỉ được xuất PDF để SP tự gửi cho khách qua kênh khác (Zalo, email cá nhân, in giấy). Hệ thống không gửi email báo giá, và không biết đã gửi hay chưa.
- Báo giá Nháp có ngày hết hạn (mặc định 3 ngày). Quá hạn mà khách không phản hồi: hệ thống tự chuyển báo giá sang Từ chối; nếu cơ hội đang xử lý thì chuyển cơ hội sang Lost; nếu cơ hội đã Won thì giữ nguyên Won. (Chức năng tự động này làm ở bước sau, KHÔNG làm ở bước hiện tại.)
- Nhãn "Phân vân · còn N ngày" chỉ là nhãn tính ra trên cơ hội của SP từ báo giá Nháp còn hạn; không lưu trong CSDL, và KHÔNG phải một giá trị của Opportunities.status.
- Lập báo giá mới trên một cơ hội chỉ khi: cơ hội đã ở giai đoạn Chốt (Stage::CLOSED), trạng thái cơ hội là InProgress hoặc Won (không phải Lost hoặc Archived), và cơ hội không còn báo giá Nháp. Khách đã mua vẫn được lập báo giá mới để mua thêm khóa.
- Xác nhận báo giá: trong một giao dịch, báo giá sang Confirmed và tạo đơn hàng (tổng tiền bằng tổng báo giá, đã thanh toán 0, còn lại bằng tổng, trạng thái InProgress, hình thức thanh toán chưa xác định).
- Hóa đơn: hình thức thanh toán (Toàn bộ / Trả góp) chọn một lần ở hóa đơn đầu tiên của đơn; phương thức (Chuyển khoản / Tiền mặt) chọn ở từng hóa đơn. Trả góp: 0 < số tiền ≤ số còn lại trừ các hóa đơn đang chờ khác. Không có chức năng hủy hóa đơn; chỉ SP sửa hóa đơn khi còn chờ thanh toán. Chuyển khoản do hệ thống kiểm tra giao dịch; tiền mặt do kế toán đối chiếu và duyệt. Mỗi hóa đơn thanh toán thành công thì thông báo SP và kế toán, gửi email hóa đơn cho khách. Cơ hội chuyển Won ở lần thanh toán thành công đầu tiên.
- Trạng thái đơn hàng trong CSDL: InProgress, Completed. Trạng thái hóa đơn: Unpaid (hiển thị "Chờ thanh toán"), Paid, Overdue.
- Kế toán xem được báo giá, đơn hàng và hóa đơn (chỉ xem; thao tác duyệt tiền mặt và xử lý giao dịch không khớp thuộc phần hóa đơn).

### 0.2. Quy trình từng bước (Tác nhân: SP = Salesperson, HT = Hệ thống, KH = Khách hàng, KT = Kế toán)
A. Lập báo giá
- HĐ1 SP: chọn cơ hội của khách có nhu cầu đăng ký khóa học, yêu cầu lập báo giá → HĐ2.
- HĐ2 HT: kiểm tra cơ hội (đã Chốt; InProgress hoặc Won; không còn báo giá Nháp). Không đạt: thông báo lý do, kết thúc. Đạt: hiển thị biểu mẫu báo giá (khách và cơ hội chỉ đọc, ngày hết hạn mặc định 3 ngày, khóa học đang kích hoạt, voucher hợp lệ kèm điều kiện, ghi chú) → HĐ3.
- HĐ3 SP: nhập thông tin báo giá → HĐ4.
- HĐ4 HT: kiểm tra (đủ trường, ít nhất một khóa, số lượng > 0, hạn không trước ngày lập, voucher đủ điều kiện). Sai → HĐ5; đúng → HĐ6.
- HĐ5 SP: sửa theo thông báo lỗi → HĐ4.
- HĐ6 HT: tính thành tiền, áp voucher, tính tổng; lưu báo giá Nháp, hiển thị chi tiết → HĐ7.
B. Đưa báo giá cho khách và nhận phản hồi
- HĐ7 SP: yêu cầu xuất PDF để gửi khách qua kênh khác → HĐ8.
- HĐ8 HT: kiểm tra báo giá còn Nháp và chưa hết hạn, tạo PDF cho SP tải; trạng thái vẫn Nháp → HĐ9.
- HĐ9 KH: phản hồi. Đồng ý → HĐ16; yêu cầu chỉnh → HĐ10; cần suy nghĩ → HĐ11; không đồng ý → HĐ12.
- HĐ10 SP: điều chỉnh báo giá Nháp → HĐ6, rồi xuất lại PDF (HĐ7).
- HĐ11 HT: theo dõi hạn, hiển thị nhãn "Phân vân · còn N ngày" trên cơ hội. Khách phản hồi → HĐ9; quá hạn → HĐ15.
- HĐ12 SP: từ chối báo giá → HĐ13.
- HĐ13 HT: báo giá sang Từ chối; cơ hội giữ nguyên → HĐ14.
- HĐ14 SP: còn nhu cầu → lập báo giá mới (HĐ1); không còn: cơ hội đang xử lý thì sang Lost, đã Won thì giữ Won; kết thúc.
- HĐ15 HT: báo giá quá hạn → tự Từ chối; cơ hội đang xử lý sang Lost, đã Won giữ Won; kết thúc.
C. Xác nhận báo giá, tạo đơn hàng
- HĐ16 SP: xác nhận báo giá → HĐ17.
- HĐ17 HT: kiểm tra (còn Nháp, chưa hết hạn, voucher còn hiệu lực, chưa có đơn). Hợp lệ: một giao dịch gồm báo giá sang Confirmed + tạo đơn hàng. Có lỗi thì hoàn tác → HĐ18.
D. Tạo hóa đơn
- HĐ18 SP: yêu cầu tạo hóa đơn từ đơn chưa thanh toán đủ → HĐ19.
- HĐ19 HT: kiểm tra hồ sơ khách và học viên (SĐT, giới tính, ngày sinh) và khách có email. Thiếu → HĐ20; đủ → HĐ21.
- HĐ20 SP: bổ sung hồ sơ → HĐ19.
- HĐ21 HT: hóa đơn đầu tiên (hình thức chưa xác định): hiển thị hộp chọn Toàn bộ / Trả góp → HĐ22; kỳ sau: bỏ qua → HĐ23.
- HĐ22 SP: chọn hình thức → HĐ23.
- HĐ23 HT: hiển thị biểu mẫu hóa đơn (Toàn bộ: số tiền = tổng đơn, khóa; Trả góp: số tiền để trống kèm số còn lại và số đang chờ; hạn thanh toán; phương thức) → HĐ24.
- HĐ24 SP: nhập số tiền, hạn, phương thức, xác nhận → HĐ25.
- HĐ25 HT: kiểm tra số tiền. Sai: báo mức tối đa → HĐ24. Đúng → HĐ26.
- HĐ26 HT: một giao dịch: tạo hóa đơn Chờ thanh toán (kỳ = kỳ lớn nhất + 1), ghi hình thức cho đơn nếu chưa có. Chuyển khoản → HĐ27; tiền mặt → HĐ30.
E. Chuyển khoản
- HĐ27 HT: hiển thị QR (tài khoản nhận, đúng số tiền, nội dung = mã hóa đơn) → HĐ28.
- HĐ28 KH: chuyển khoản → HĐ29.
- HĐ29 HT: nhận thông báo giao dịch từ ngân hàng/cổng QR, so khớp số tiền và nội dung với hóa đơn Chờ thanh toán. Khớp → HĐ33. Không khớp hoặc hóa đơn không còn chờ: không ghi nhận, lưu giao dịch, thông báo SP và KT → HĐ36.
F. Tiền mặt
- HĐ30 HT: hiển thị "Đã gửi kế toán, chờ xác nhận", đưa hóa đơn vào danh sách chờ của KT → HĐ31.
- HĐ31 KH: nộp tiền mặt cho KT → HĐ32.
- HĐ32 KT: đối chiếu. Khớp: duyệt → HĐ33. Không khớp: không duyệt, báo SP điều chỉnh số tiền → HĐ36.
G. Ghi nhận thanh toán
- HĐ33 HT: một giao dịch: hóa đơn sang Đã thanh toán (kèm ngày thanh toán; KT duyệt nếu tiền mặt); đơn tăng số đã thanh toán, tính lại số còn lại; đơn InProgress nếu còn nợ, Completed nếu hết nợ. Thông báo SP và KT → HĐ34.
- HĐ34 HT: gửi thông tin hóa đơn đến email khách hàng thuộc cơ hội, cho mỗi hóa đơn thanh toán thành công. Lỗi gửi: ghi nhận lỗi, không hoàn tác thanh toán, báo SP gửi lại → HĐ35.
- HĐ35 HT: nếu cơ hội chưa Won thì chuyển Won; đã Won giữ nguyên → HĐ37.
- HĐ36 SP: điều chỉnh hóa đơn chờ thanh toán (đổi phương thức chỉ khi khách chưa chuyển khoản; hoặc sửa số tiền) → HĐ25. Hợp lệ: chuyển khoản → HĐ27 (QR mới), tiền mặt → HĐ30.
- HĐ37 HT: còn nợ → HĐ38; hết nợ → HĐ39.
- HĐ38 HT: đơn còn nợ, đến kỳ SP tạo hóa đơn tiếp (không hỏi lại hình thức) → HĐ18.
- HĐ39 HT: đơn Completed, ẩn thao tác tạo hóa đơn. Khách mua thêm thì SP quay lại HĐ1.

### 0.3. Các điểm nhóm CHƯA chốt (không được tự quyết, nếu gặp trong khi làm thì báo lại)
- Trạng thái hóa đơn Overdue chưa có bước nào chuyển sang.
- Cơ hội đã Lost mà khách quay lại: tạo cơ hội mới hay mở lại cơ hội.

### 0.4. Các điểm đã chốt sau khi thống nhất quy trình
- 17/10/2026: cột SalesOrders.payment_type cho phép NULL (= chưa xác định). Đơn tạo ở HĐ17 có payment_type NULL; hóa đơn đầu tiên (HĐ21 – HĐ22) ghi Full hoặc Installment cho đơn. CHECK vẫn chỉ nhận Full / Installment khi cột có giá trị.
