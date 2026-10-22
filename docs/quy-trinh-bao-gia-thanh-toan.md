## 0. Quy trình nghiệp vụ đã thống nhất (bắt buộc tuân theo)

Bản cập nhật 18/10/2026 (người lập: Thái Trâm Anh), thay cho bản đầu tiên của nhóm; cập nhật tiếp ngày 22 và 23/10/2026: còn 39 hoạt động, không có lane Khách hàng. Code phải tuân theo file này; số HĐ trong comment code được viết theo bản đầu tiên, khác số HĐ ở đây.

### 0.1. Quy tắc nghiệp vụ

**Chung**
- Một cơ hội có 0 hoặc nhiều báo giá; một báo giá có 0 hoặc 1 đơn hàng; một đơn hàng có 1 hoặc nhiều hóa đơn.
- Kế toán xem được báo giá, đơn hàng và hóa đơn (chỉ xem); kế toán xác nhận tiền mặt và xác nhận chuyển khoản lệch số tiền.

**Báo giá**
- Báo giá chỉ có 3 trạng thái: Draft (Nháp), Confirmed (Đã xác nhận), Rejected (Từ chối). KHÔNG có hủy hay xóa báo giá. Báo giá lập sai thì sửa khi còn Nháp, hoặc Từ chối.
- Chức năng Tạo báo giá chỉ hiển thị cho Salesperson phụ trách cơ hội, khi: (a) cơ hội ở giai đoạn 1 Data chưa tương tác đã liên hệ ban đầu thành công (khách đăng ký ngay), hoặc ở giai đoạn 3 Data đã xử lý đã liên hệ chốt đăng ký thành công và còn trong hạn quyết định (giống quy trình xử lý cơ hội của nhóm); chọn Tạo báo giá thì cơ hội chuyển sang Chốt; hoặc (b) cơ hội đã ở Chốt (Stage::CLOSED), trạng thái InProgress hoặc Won (không phải Lost, Archived) và không còn báo giá Nháp. Từ giai đoạn Chốt, cơ hội không đổi người phụ trách. Hệ thống vẫn kiểm tra lại khi mở biểu mẫu (vào thẳng URL, trang mở từ trước). Khách đã mua vẫn được lập báo giá mới để mua thêm khóa.
- Khi lập báo giá, hệ thống kiểm tra luôn hồ sơ khách hàng: khách phải có số điện thoại và email (để gửi email hóa đơn sau này); thiếu email thì Salesperson bổ sung ngay trên biểu mẫu báo giá.
- Báo giá không có hình thức hay phương thức thanh toán. Tổng tiền báo giá KHÔNG lưu trong CSDL, luôn tính ra: tổng line_total của các dòng chi tiết (đã gồm VAT) trừ giảm giá voucher (voucher phần trăm: làm tròn 2 số lẻ; voucher số tiền cố định: không vượt quá tạm tính). Dùng Voucher::discountFor().
- Voucher chỉ cần hợp lệ tại ngày lập báo giá (biểu mẫu chỉ hiện voucher đang hoạt động và còn trong thời gian áp dụng). Khách đã đồng ý mức giá nào thì giữ nguyên: voucher hết hạn hoặc bị ngưng sau đó vẫn được giữ khi sửa và khi xác nhận báo giá.
- Báo giá chỉ được xuất PDF để Salesperson tự gửi cho khách qua kênh khác (Zalo, email cá nhân, in giấy). Hệ thống không gửi email báo giá, và không biết đã gửi hay chưa.
- Báo giá Nháp có ngày hết hạn (mặc định 3 ngày, không trước ngày lập). Quá hạn mà khách không phản hồi: hệ thống tự chuyển báo giá sang Từ chối (lệnh quotations:expire chạy hằng ngày); cơ hội đang xử lý chuyển sang Lost; cơ hội đã Won giữ nguyên Won.
- Khách muốn đổi khóa học, số lượng hoặc nội dung thì SP sửa báo giá Nháp, không từ chối rồi lập lại. Khách không đồng ý thì SP từ chối báo giá và quy trình kết thúc: cơ hội đang xử lý chuyển Lost, đã Won giữ nguyên (không hỏi lại nhu cầu).
- Nhãn "Phân vân · còn N ngày" chỉ là nhãn tính ra, không lưu trong CSDL và KHÔNG phải một giá trị của Opportunities.status: ở Chốt tính từ hạn báo giá Nháp; ở giai đoạn 3 là hạn quyết định của quy trình xử lý cơ hội.
- Xác nhận báo giá: trong một giao dịch, báo giá sang Confirmed và tạo đơn hàng (tổng tiền bằng tổng báo giá, đã thanh toán 0, còn lại bằng tổng, trạng thái InProgress, hình thức thanh toán NULL = chưa xác định). Khi xác nhận chỉ kiểm tra báo giá còn Nháp, còn hạn và chưa có đơn; không kiểm tra lại voucher.

**Đơn hàng và hóa đơn**
- Trạng thái đơn hàng trong CSDL: InProgress, Completed. Trạng thái hóa đơn: Unpaid (hiển thị "Chờ thanh toán"), Paid, Overdue.
- Hình thức thanh toán (Toàn bộ / Trả góp, cột SalesOrders.payment_type) gắn với đơn hàng, chọn một lần ở hóa đơn đầu tiên, sau đó không đổi. Phương thức thanh toán (Chuyển khoản / Tiền mặt, cột Invoices.payment_method) chọn ở từng hóa đơn, mỗi kỳ có thể khác nhau.
- Toàn bộ: số tiền hóa đơn tự điền bằng phần còn lại của đơn, không sửa. Trả góp: 0 < số tiền ≤ còn lại trừ tổng các hóa đơn Chờ thanh toán khác của đơn. Hạn thanh toán mặc định 7 ngày, không trước ngày lập.
- Chỉ hóa đơn Chờ thanh toán mới nhận thanh toán; hóa đơn Overdue không nhận và không tính vào khoản đang chờ, để Salesperson lập hóa đơn thay thế.
- Không có chức năng hủy hóa đơn. Chỉ Salesperson lập hóa đơn được sửa (số tiền với Trả góp, hạn, phương thức) khi hóa đơn còn Chờ thanh toán; chỉ đổi phương thức khi khách chưa chuyển khoản.
- Chuyển khoản: hệ thống tạo mã QR (tài khoản nhận, đúng số tiền, nội dung = mã hóa đơn) và tự đối chiếu giao dịch ngân hàng; khớp đúng số tiền và mã hóa đơn thì tự ghi nhận, không có kế toán tham gia.
- Chuyển khoản lệch số tiền (ngoại lệ): hệ thống KHÔNG ghi nhận, báo lỗi cho Kế toán và Salesperson (chuông thông báo và trang hóa đơn). Kế toán kiểm tra sao kê; chuyển thiếu thì Salesperson nhắc khách chuyển bổ sung vào tài khoản trung tâm; khi đã đủ, Kế toán xác nhận đã nhận chuyển khoản. Số tiền hóa đơn giữ nguyên, hệ thống không cộng dồn các giao dịch lệch. Chuyển thừa và hoàn tiền nằm ngoài phạm vi dự án.
- Tiền mặt: hóa đơn vào danh sách chờ của Kế toán; Kế toán nhận tiền và xác nhận thanh toán, hóa đơn ghi mã kế toán. Không có nhánh "không duyệt".
- Mỗi hóa đơn thanh toán thành công: cập nhật đã thanh toán / còn lại của đơn (hết nợ thì đơn Completed), thông báo Salesperson và Kế toán, gửi email hóa đơn cho khách (lỗi gửi chỉ ghi log, không hoàn tác thanh toán). Ở lần thanh toán thành công đầu tiên (trả toàn bộ hoặc kỳ trả góp đầu): cơ hội chuyển Won và khách trở thành học viên; các kỳ sau không tạo thêm học viên.
- Công nợ (báo cáo doanh thu & thanh toán): phần còn lại của đơn InProgress; hóa đơn Chờ thanh toán được tính trước, hóa đơn Overdue chỉ tính phần chưa có hóa đơn thay thế, nên không tính trùng.

### 0.2. Quy trình từng bước (Tác nhân: SP = Salesperson, HT = Hệ thống, KT = Kế toán)

Quy trình nội bộ, không có lane Khách hàng: việc của khách (phản hồi báo giá, chuyển khoản, nộp tiền mặt) nằm trong bước của SP, HT hoặc KT tiếp nhận. Bắt đầu khi khách đồng ý đăng ký ở giai đoạn 1 (Data chưa tương tác) hoặc giai đoạn 3 (Data đã xử lý) của quy trình tiếp nhận và xử lý cơ hội bán hàng. Gồm 39 hoạt động.

A. Lập báo giá
- HĐ1 SP: chọn cơ hội của khách hàng đồng ý đăng ký khóa học.
- HĐ2 HT: kiểm tra điều kiện lập báo giá (xem 0.1). Đủ → hiển thị chức năng Tạo báo giá → HĐ3. Không đủ → kết thúc.
- HĐ3 SP: chọn Tạo báo giá.
- HĐ4 HT: chuyển cơ hội sang giai đoạn Chốt và hiển thị biểu mẫu báo giá.
- HĐ5 SP: nhập thông tin báo giá: khóa học, số lượng, voucher (nếu có), ngày hết hạn, ghi chú; bổ sung email khách hàng nếu hồ sơ còn thiếu.
- HĐ6 HT: kiểm tra thông tin báo giá và hồ sơ khách hàng. Thiếu hoặc chưa hợp lệ → HĐ7. Đầy đủ và hợp lệ → HĐ8.
- HĐ7 HT: thông báo lỗi, yêu cầu SP điều chỉnh → HĐ5.
- HĐ8 HT: tính và hiển thị thành tiền từng khóa học, giảm giá voucher và tổng tiền báo giá.
- HĐ9 SP: xác nhận lại thông tin và chọn lưu báo giá.
- HĐ10 HT: lưu báo giá ở trạng thái Nháp và hiển thị chi tiết báo giá.

B. Gửi báo giá và nhận phản hồi của khách
- HĐ11 SP: xuất báo giá dạng PDF.
- HĐ12 SP: gửi và xác nhận báo giá với khách hàng. Khách không đồng ý → HĐ13. Đồng ý → HĐ19.
- HĐ13 SP: tư vấn và điều chỉnh báo giá với khách hàng. Khách muốn đổi khóa học, số lượng hoặc nội dung → HĐ14. Cần suy nghĩ thêm → HĐ15. Không đồng ý → HĐ17.
- HĐ14 SP: chọn chỉnh sửa báo giá và cập nhật theo yêu cầu của khách → HĐ6.
- HĐ15 HT: gắn nhãn "Phân vân · còn N ngày" cho cơ hội và theo dõi hạn báo giá. Khách phản hồi trước hạn → HĐ12. Quá hạn mà khách không phản hồi → HĐ16.
- HĐ16 HT: tự động chuyển báo giá sang Từ chối; cơ hội đang xử lý chuyển sang Lost, đã Won giữ nguyên → kết thúc.
- HĐ17 SP: chọn Từ chối báo giá.
- HĐ18 HT: chuyển báo giá sang Từ chối; cơ hội đang xử lý chuyển sang Lost, đã Won giữ nguyên → kết thúc.

C. Xác nhận báo giá, tạo đơn hàng
- HĐ19 SP: chọn Xác nhận báo giá.
- HĐ20 HT: kiểm tra hạn của báo giá. Đã hết hạn → HĐ16. Còn hạn → HĐ21.
- HĐ21 HT: chuyển báo giá sang Đã xác nhận.
- HĐ22 HT: tạo đơn hàng từ báo giá (tổng tiền bằng tổng báo giá, đã thanh toán 0, trạng thái Đang xử lý, hình thức thanh toán chưa xác định). HĐ21 và HĐ22 nằm trong một giao dịch.

D. Tạo hóa đơn
- HĐ23 SP: chọn Tạo hóa đơn cho đơn hàng còn nợ.
- HĐ24 HT: kiểm tra hình thức thanh toán của đơn hàng. Hóa đơn đầu tiên → HĐ25. Đơn đã có hình thức → HĐ26.
- HĐ25 SP: chọn hình thức thanh toán: Toàn bộ / Trả góp.
- HĐ26 SP: nhập số tiền, hạn thanh toán và chọn phương thức thanh toán: Chuyển khoản / Tiền mặt (Toàn bộ: số tiền tự điền bằng phần còn lại của đơn).
- HĐ27 HT: kiểm tra số tiền hóa đơn. Vượt quá số tiền được phép (còn lại trừ các hóa đơn đang chờ) → thông báo số tiền tối đa → HĐ26. Hợp lệ → HĐ28.
- HĐ28 HT: tạo hóa đơn Chờ thanh toán (ghi hình thức thanh toán cho đơn nếu là hóa đơn đầu tiên). Chuyển khoản → HĐ29. Tiền mặt → HĐ33.

E. Chuyển khoản
- HĐ29 HT: tạo và hiển thị mã QR thanh toán cho khách hàng (tài khoản nhận, đúng số tiền, nội dung là mã hóa đơn).
- HĐ30 HT: nhận thông báo giao dịch từ ngân hàng khi khách chuyển khoản và đối chiếu với hóa đơn. Khớp số tiền và nội dung → HĐ35. Không khớp → không ghi nhận thanh toán, báo lỗi cho KT và SP → HĐ31.
- HĐ31 KT: kiểm tra hóa đơn và sao kê ngân hàng. Khách chuyển thiếu → HĐ32. Khách đã chuyển đủ số tiền hóa đơn → xác nhận đã nhận chuyển khoản → HĐ35.
- HĐ32 SP: liên hệ khách hàng chuyển bổ sung phần còn thiếu vào tài khoản của trung tâm → HĐ31.

F. Tiền mặt
- HĐ33 HT: chuyển hóa đơn vào danh sách chờ duyệt tiền mặt của KT.
- HĐ34 KT: nhận tiền mặt khách hàng nộp theo hóa đơn và xác nhận thanh toán → HĐ35.

G. Ghi nhận thanh toán
- HĐ35 HT: ghi nhận thanh toán: hóa đơn sang Đã thanh toán (kèm ngày thanh toán; mã kế toán nếu tiền mặt), cập nhật số tiền đã thanh toán và còn lại của đơn hàng.
- HĐ36 HT: thông báo cho SP và KT, gửi hóa đơn đến email khách hàng.
- HĐ37 HT: kiểm tra trạng thái cơ hội. Lần thanh toán đầu tiên → chuyển cơ hội sang Won, khách hàng trở thành học viên → HĐ38. Đã Won → giữ nguyên → HĐ38.
- HĐ38 HT: kiểm tra số tiền còn lại của đơn hàng. Còn nợ → đơn giữ Đang xử lý, chờ kỳ thanh toán tiếp theo → HĐ23. Đã thanh toán đủ → HĐ39.
- HĐ39 HT: chuyển đơn hàng sang Hoàn tất → kết thúc. Khách mua thêm thì SP quay lại HĐ1.

### 0.3. Các điểm nhóm CHƯA chốt (không được tự quyết, nếu gặp trong khi làm thì báo lại)
- Trạng thái hóa đơn Overdue chưa có bước nào chuyển sang.
- Cơ hội đã Lost mà khách quay lại: tạo cơ hội mới hay mở lại cơ hội.

### 0.4. Các điểm đã chốt sau khi thống nhất quy trình
- 17/10/2026: cột SalesOrders.payment_type cho phép NULL (= chưa xác định). Đơn tạo ở HĐ22 có payment_type NULL; hóa đơn đầu tiên (HĐ24 – HĐ25) ghi Full hoặc Installment cho đơn. CHECK vẫn chỉ nhận Full / Installment khi cột có giá trị.
- 17/10/2026: hóa đơn chuyển khoản không có kế toán (accountant_id NULL, có CHECK trong odin.sql). Hóa đơn chuyển khoản lệch do kế toán xác nhận thủ công (HĐ31) cũng để accountant_id NULL; người xác nhận được ghi vào log thanh toán.
- 18/10/2026: chức năng Tạo báo giá chỉ hiển thị khi cơ hội đủ điều kiện (HĐ2).
- 22/10/2026: khách trở thành học viên ở lần thanh toán thành công đầu tiên (HĐ37), không chờ đơn Hoàn tất; dữ liệu mẫu odin.sql / odin_demo_bulk.sql đã bổ sung học viên cho 12 đơn trả góp đã đóng tiền (STD145 – STD156).
- 22/10/2026: lập báo giá bắt đầu ở giai đoạn 1 và giai đoạn 3 giống quy trình xử lý cơ hội (Hải Linh); chọn Tạo báo giá thì cơ hội chuyển sang Chốt (HĐ4).
- 18/10/2026: kiểm tra hồ sơ khách hàng (email) chuyển lên bước lập báo giá (HĐ5 – HĐ6), không còn bước kiểm tra riêng khi tạo hóa đơn.
- 18/10/2026: voucher được giữ theo ngày lập báo giá; khi xác nhận không kiểm tra lại voucher (HĐ20 chỉ kiểm tra hạn báo giá).
- 18/10/2026: chuyển khoản lệch số tiền → báo lỗi cho kế toán và SP, kế toán kiểm tra sao kê và xác nhận thủ công khi khách đã chuyển đủ (HĐ30 – HĐ32). Không thêm cột vào CSDL, không cộng dồn giao dịch lệch; chuyển thừa và hoàn tiền nằm ngoài phạm vi.
- 18/10/2026: tiền mặt không có nhánh "không duyệt"; kế toán nhận tiền và xác nhận (HĐ34).
- 22/10/2026: bỏ lane Khách hàng vì đây là quy trình nội bộ.
- 23/10/2026: khách không đồng ý báo giá thì SP từ chối và quy trình kết thúc (cơ hội đang xử lý → Lost, đã Won giữ nguyên; HĐ17 – HĐ18); khách muốn đổi khóa học / số lượng thì sửa báo giá Nháp (HĐ14). Bỏ bước hỏi "khách còn nhu cầu không"; quy trình còn 39 hoạt động.
