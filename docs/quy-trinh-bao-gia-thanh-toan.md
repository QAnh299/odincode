## 0. Quy trình nghiệp vụ đã thống nhất (bắt buộc tuân theo)

Bản cập nhật 18/10/2026 (người lập: Thái Trâm Anh), thay cho bản đầu tiên của nhóm; cập nhật tiếp ngày 22, 23 và 25/10/2026: còn 37 hoạt động, không có lane Khách hàng. Code phải tuân theo file này; số HĐ trong comment code được viết theo bản đầu tiên, khác số HĐ ở đây.

### 0.1. Quy tắc nghiệp vụ

**Chung**
- Một cơ hội có 0, 1 hoặc nhiều báo giá (mỗi lúc chỉ một báo giá Nháp); một báo giá có 0 hoặc 1 đơn hàng; một đơn hàng có 1 hoặc nhiều hóa đơn.
- Kế toán xem được báo giá, đơn hàng và hóa đơn (chỉ xem); kế toán xác nhận tiền mặt và xác nhận chuyển khoản lệch số tiền.

**Báo giá**
- Báo giá chỉ có 3 trạng thái: Draft (Nháp), Confirmed (Đã xác nhận), Rejected (Từ chối). KHÔNG có hủy hay xóa báo giá. Báo giá lập sai thì sửa khi còn Nháp, hoặc Từ chối.
- Chỉ lập báo giá từ giai đoạn 1 và giai đoạn 3 (giống quy trình xử lý cơ hội của nhóm). Chức năng Tạo báo giá chỉ hiển thị cho Salesperson phụ trách cơ hội khi: cơ hội ở giai đoạn 1 Data chưa tương tác đã liên hệ ban đầu thành công (khách đăng ký ngay), hoặc ở giai đoạn 3 Data đã xử lý đã liên hệ chốt đăng ký thành công và còn trong hạn quyết định. Chọn Tạo báo giá thì cơ hội chuyển sang Chốt và mở biểu mẫu. Cơ hội đã ở Chốt vẫn lập thêm báo giá được khi cơ hội đang xử lý hoặc Won và không còn báo giá Nháp nào (ví dụ khách đã là học viên đăng ký thêm khóa học). Từ giai đoạn Chốt, cơ hội không đổi người phụ trách.
- Khi lập báo giá, hệ thống kiểm tra luôn hồ sơ khách hàng: khách phải có số điện thoại và email (để gửi email hóa đơn sau này); thiếu email thì Salesperson bổ sung ngay trên biểu mẫu báo giá.
- Báo giá không có hình thức hay phương thức thanh toán. Tổng tiền báo giá KHÔNG lưu trong CSDL, luôn tính ra: tổng line_total của các dòng chi tiết (đã gồm VAT) trừ giảm giá voucher (voucher phần trăm: làm tròn 2 số lẻ; voucher số tiền cố định: không vượt quá tạm tính). Dùng Voucher::discountFor().
- Voucher chỉ cần hợp lệ tại ngày lập báo giá (biểu mẫu chỉ hiện voucher đang hoạt động và còn trong thời gian áp dụng). Khách đã đồng ý mức giá nào thì giữ nguyên: voucher hết hạn hoặc bị ngưng sau đó vẫn được giữ khi sửa và khi xác nhận báo giá.
- Xuất PDF để Salesperson tự gửi cho khách qua kênh khác (Zalo, email cá nhân, in giấy): Salesperson lập báo giá, báo giá Nháp (kể cả đã quá hạn) hoặc Đã xác nhận. Hệ thống không gửi email báo giá, và không biết đã gửi hay chưa.
- Báo giá Nháp có ngày hết hạn (mặc định 3 ngày, không trước ngày lập). Quá hạn thì báo giá KHÔNG tự đổi trạng thái: hệ thống gắn nhãn "Quá hạn" trên danh sách và chi tiết báo giá, và nhắc Salesperson lập báo giá mỗi ngày (chuông thông báo) cho tới khi SP xử lý. SP xử lý: khách còn nhu cầu thì chỉnh sửa ngày hết hạn để gia hạn (báo giá Nháp đã quá hạn vẫn được chỉnh sửa); khách không mua thì từ chối.
- Khách muốn đổi khóa học, số lượng hoặc nội dung thì SP sửa báo giá Nháp, không từ chối rồi lập lại. Khách cần suy nghĩ thêm thì báo giá giữ trạng thái Nháp, SP tiếp tục chăm sóc. Khách không đồng ý thì SP từ chối báo giá và quy trình kết thúc: cơ hội đang xử lý chuyển Lost, đã Won giữ nguyên (không hỏi lại nhu cầu). Khách không phản hồi đến khi báo giá quá hạn thì SP từ chối với lý do "khách không phản hồi": cơ hội đang xử lý chuyển Archived (Lưu trữ), cùng quy tắc với "không liên lạc được" ở quy trình xử lý cơ hội. Khách phản hồi muộn trước khi SP từ chối thì SP gia hạn báo giá (HĐ14).
- Không còn nhãn "Phân vân · còn N ngày" cho báo giá (nhãn này chỉ còn ở giai đoạn 3, là hạn quyết định của quy trình xử lý cơ hội).
- Xác nhận báo giá: báo giá đã quá hạn thì hệ thống chặn lại và thông báo "Báo giá đã quá hạn, hãy gia hạn trước khi xác nhận", không tự chuyển Lost. Báo giá còn hạn thì trong một giao dịch, báo giá sang Confirmed và tạo đơn hàng gắn với báo giá (tổng tiền bằng tổng báo giá, đã thanh toán 0, còn lại bằng tổng, trạng thái InProgress, hình thức thanh toán NULL = chưa xác định). Khi xác nhận không kiểm tra lại voucher.

**Đơn hàng và hóa đơn**
- Trạng thái đơn hàng trong CSDL: InProgress, Completed. Trạng thái hóa đơn: Unpaid (hiển thị "Chờ thanh toán"), Paid, Overdue.
- Đơn hàng có trang chi tiết (mọi vai trò, chỉ xem, phạm vi dữ liệu như danh sách đơn): thông tin đơn (tổng tiền, đã thanh toán, còn lại, hình thức, báo giá nguồn, nhân viên), khách hàng, khóa học theo báo giá đã xác nhận và lịch sử các kỳ hóa đơn. Mở bằng mã đơn trên danh sách đơn hàng hoặc ở khối Đơn hàng của chi tiết báo giá.
- Tạo hóa đơn từ Quản lý đơn hàng (hoặc từ chi tiết đơn hàng / chi tiết báo giá / chi tiết hóa đơn): chỉ Salesperson lập báo giá của đơn, đơn Đang xử lý và còn phần chưa lập hóa đơn.
- Hình thức thanh toán (Toàn bộ / Trả góp, cột SalesOrders.payment_type) gắn với đơn hàng, chọn một lần ở hóa đơn đầu tiên, sau đó không đổi. Phương thức thanh toán (Chuyển khoản / Tiền mặt, cột Invoices.payment_method) chọn khi tạo từng hóa đơn, mỗi kỳ có thể khác nhau.
- Toàn bộ: số tiền hóa đơn tự điền bằng phần còn lại của đơn, không sửa. Trả góp: số tiền mặc định bằng số còn lại được lập, SP nhập số tiền của kỳ này; 0 < số tiền ≤ còn lại trừ tổng các hóa đơn Chờ thanh toán khác của đơn. Hạn thanh toán mặc định 7 ngày, không trước ngày lập.
- Chỉ hóa đơn Chờ thanh toán mới nhận thanh toán; hóa đơn Overdue không nhận và không tính vào khoản đang chờ, để Salesperson lập hóa đơn thay thế.
- Không có chức năng hủy hóa đơn. Chỉ Salesperson lập hóa đơn được sửa khi hóa đơn còn Chờ thanh toán: sửa hạn thanh toán, và số tiền với đơn Trả góp. Không đổi được phương thức thanh toán khi sửa. Hóa đơn chuyển khoản sửa số tiền thì mã QR tạo lại theo số tiền mới.
- Chuyển khoản: hệ thống tạo mã QR (tài khoản nhận, đúng số tiền, nội dung = mã hóa đơn) và tự đối chiếu giao dịch ngân hàng; khớp đúng số tiền và mã hóa đơn thì tự ghi nhận, không có kế toán tham gia.
- Chuyển khoản lệch số tiền (ngoại lệ): hệ thống KHÔNG ghi nhận, báo lỗi cho Kế toán và Salesperson (chuông thông báo và trang hóa đơn). Kế toán kiểm tra sao kê; chuyển thiếu thì Salesperson nhắc khách chuyển bổ sung vào tài khoản trung tâm; khi đã đủ, Kế toán xác nhận đã nhận chuyển khoản. Số tiền hóa đơn giữ nguyên, hệ thống không cộng dồn các giao dịch lệch. Chuyển thừa và hoàn tiền nằm ngoài phạm vi dự án.
- Tiền mặt: hóa đơn vào danh sách chờ của Kế toán; Kế toán nhận tiền và xác nhận thanh toán, hóa đơn ghi mã kế toán. Không có nhánh "không duyệt".
- Mỗi hóa đơn thanh toán thành công: cập nhật đã thanh toán / còn lại của đơn (hết nợ thì đơn Completed), thông báo Salesperson và Kế toán, gửi email hóa đơn cho khách (lỗi gửi chỉ ghi log, không hoàn tác thanh toán). Ở lần thanh toán thành công đầu tiên (trả toàn bộ hoặc kỳ trả góp đầu): cơ hội chuyển Won và khách trở thành học viên; các kỳ sau không tạo thêm học viên.
- Công nợ (báo cáo doanh thu & thanh toán): phần còn lại của đơn InProgress; hóa đơn Chờ thanh toán được tính trước, hóa đơn Overdue chỉ tính phần chưa có hóa đơn thay thế, nên không tính trùng.

### 0.2. Quy trình từng bước (Tác nhân: SP = Salesperson, HT = Hệ thống, KT = Kế toán)

Quy trình nội bộ, không có lane Khách hàng: việc của khách (phản hồi báo giá, chuyển khoản, nộp tiền mặt) nằm trong bước của SP, HT hoặc KT tiếp nhận. Bắt đầu khi khách đồng ý đăng ký ở giai đoạn 1 (Data chưa tương tác) hoặc giai đoạn 3 (Data đã xử lý) của quy trình tiếp nhận và xử lý cơ hội bán hàng. Gồm 37 hoạt động. Việc nhắc báo giá quá hạn mỗi ngày là quy tắc chạy nền (xem 0.1), không phải một hoạt động trong luồng.

A. Lập báo giá
- HĐ1 SP: chọn cơ hội của khách hàng đồng ý đăng ký khóa học (giai đoạn 1 hoặc giai đoạn 3).
- HĐ2 HT: kiểm tra điều kiện lập báo giá (xem 0.1). Đủ → hiển thị chức năng Tạo báo giá → HĐ3. Không đủ → kết thúc.
- HĐ3 SP: chọn Tạo báo giá.
- HĐ4 HT: chuyển cơ hội sang giai đoạn Chốt và hiển thị biểu mẫu báo giá.
- HĐ5 SP: nhập thông tin báo giá: khóa học, số lượng, voucher (nếu có), ngày hết hạn, ghi chú; bổ sung email khách hàng nếu hồ sơ còn thiếu.
- HĐ6 HT: tự động tính và hiển thị thành tiền từng khóa học, giảm giá voucher và tổng tiền báo giá ngay khi SP chọn khóa học, số lượng hoặc voucher.
- HĐ7 SP: chọn lưu báo giá.
- HĐ8 HT: kiểm tra thông tin báo giá và hồ sơ khách hàng. Thiếu hoặc chưa hợp lệ → HĐ9. Đầy đủ và hợp lệ → HĐ10.
- HĐ9 HT: không lưu, thông báo lỗi, yêu cầu SP điều chỉnh → HĐ5.
- HĐ10 HT: lưu báo giá ở trạng thái Nháp và hiển thị chi tiết báo giá.

B. Gửi báo giá và nhận phản hồi của khách
- HĐ11 SP: xuất báo giá dạng PDF.
- HĐ12 SP: gửi và xác nhận báo giá với khách hàng. Khách không đồng ý → HĐ13. Đồng ý → HĐ17. Khách chưa phản hồi → báo giá giữ Nháp, chờ khách phản hồi: khách phản hồi → xác định lại ở HĐ12; báo giá quá hạn mà khách vẫn không phản hồi (hệ thống nhắc SP) → HĐ15. Khách phản hồi muộn (báo giá đã quá hạn) mà đồng ý thì SP gia hạn (HĐ14).
- HĐ13 SP: tư vấn và điều chỉnh báo giá với khách hàng. Khách muốn đổi khóa học, số lượng hoặc nội dung → HĐ14. Cần suy nghĩ thêm → báo giá giữ Nháp, chờ khách phản hồi như trường hợp chưa phản hồi ở HĐ12. Không đồng ý → HĐ15.
- HĐ14 SP: chọn chỉnh sửa báo giá để cập nhật theo yêu cầu của khách hoặc gia hạn ngày hết hạn; biểu mẫu hiện thông tin hiện tại → HĐ5 (quy trình con Nhập và lưu báo giá).
- HĐ15 SP: chọn Từ chối báo giá và lý do (khách không đồng ý / khách không phản hồi đến khi báo giá quá hạn).
- HĐ16 HT: chuyển báo giá sang Từ chối; cơ hội đang xử lý chuyển sang Lost (không đồng ý) hoặc Archived (không phản hồi), đã Won giữ nguyên → kết thúc.

C. Xác nhận báo giá, tạo đơn hàng
- HĐ17 SP: chọn Xác nhận báo giá.
- HĐ18 HT: kiểm tra hạn của báo giá. Đã quá hạn → thông báo "Báo giá đã quá hạn, hãy gia hạn trước khi xác nhận", báo giá giữ Nháp → HĐ14. Còn hạn → HĐ19.
- HĐ19 HT: chuyển báo giá sang Đã xác nhận.
- HĐ20 HT: tạo đơn hàng gắn với báo giá (tổng tiền bằng tổng báo giá, đã thanh toán 0, trạng thái Đang xử lý, hình thức thanh toán chưa xác định). HĐ19 và HĐ20 nằm trong một giao dịch.

D. Tạo hóa đơn
- HĐ21 SP: chọn Tạo hóa đơn trên đơn hàng còn nợ.
- HĐ22 HT: kiểm tra hình thức thanh toán của đơn hàng. Hóa đơn đầu tiên → HĐ23. Đơn đã có hình thức Toàn bộ → HĐ24. Đã có Trả góp → HĐ25.
- HĐ23 SP: chọn hình thức thanh toán. Toàn bộ → HĐ24. Trả góp → HĐ25.
- HĐ24 HT: tự điền số tiền bằng số còn được lập của đơn (còn lại trừ các hóa đơn đang chờ), không cho sửa → HĐ26.
- HĐ25 SP: nhập số tiền của kỳ này (mặc định bằng số còn được lập).
- HĐ26 SP: nhập hạn thanh toán, chọn phương thức thanh toán: Chuyển khoản / Tiền mặt, chọn Tạo hóa đơn.
- HĐ27 HT: kiểm tra thông tin hóa đơn. Chưa hợp lệ (số tiền trả góp vượt số được phép, hạn trước ngày lập, chưa chọn phương thức) → thông báo lỗi (kèm số tiền tối đa nếu vượt) → Toàn bộ → HĐ24, Trả góp → HĐ25. Hợp lệ → HĐ28.
- HĐ28 HT: tạo hóa đơn Chờ thanh toán (ghi hình thức thanh toán cho đơn nếu là hóa đơn đầu tiên). Chuyển khoản → HĐ29. Tiền mặt → HĐ33.

E. Chuyển khoản
- HĐ29 HT: tạo và hiển thị mã QR thanh toán cho khách hàng (tài khoản nhận, đúng số tiền, nội dung là mã hóa đơn).
- HĐ30 HT: nhận thông báo giao dịch từ ngân hàng khi khách chuyển khoản và đối chiếu với hóa đơn. Khớp số tiền và nội dung → HĐ35. Không khớp → không ghi nhận thanh toán, báo lỗi cho KT và SP → HĐ31.
- HĐ31 KT: kiểm tra hóa đơn và sao kê ngân hàng. Khách chuyển thiếu → HĐ32. Khách đã chuyển đủ số tiền hóa đơn → xác nhận đã nhận chuyển khoản → HĐ35.
- HĐ32 SP: liên hệ khách hàng chuyển bổ sung phần còn thiếu vào tài khoản của trung tâm → HĐ30 (hệ thống đối chiếu giao dịch mới).

F. Tiền mặt
- HĐ33 HT: chuyển hóa đơn vào danh sách chờ duyệt tiền mặt của KT.
- HĐ34 KT: nhận tiền mặt khách hàng nộp theo hóa đơn và xác nhận thanh toán → HĐ35.

G. Ghi nhận thanh toán
- HĐ35 HT: ghi nhận thanh toán: hóa đơn sang Đã thanh toán (kèm ngày thanh toán; mã kế toán nếu tiền mặt), cập nhật số tiền đã thanh toán và còn lại của đơn hàng.
- HĐ36 HT: thông báo cho SP và KT, gửi hóa đơn đến email khách hàng.
- HĐ37 HT: kiểm tra trạng thái cơ hội. Lần thanh toán đầu tiên → chuyển cơ hội sang Won, khách hàng trở thành học viên → HĐ38. Đã Won → giữ nguyên → HĐ38.
- HĐ38 HT: kiểm tra số tiền còn lại của đơn hàng. Còn nợ → đơn giữ Đang xử lý, chờ kỳ thanh toán tiếp theo → HĐ21. Đã thanh toán đủ → HĐ39.
- HĐ39 HT: chuyển đơn hàng sang Hoàn tất → kết thúc.

### 0.3. Các điểm nhóm CHƯA chốt (không được tự quyết, nếu gặp trong khi làm thì báo lại)
- Trạng thái hóa đơn Overdue chưa có bước nào chuyển sang.
- Cơ hội đã Lost mà khách quay lại: tạo cơ hội mới hay mở lại cơ hội.

### 0.4. Các điểm đã chốt sau khi thống nhất quy trình
- 17/10/2026: cột SalesOrders.payment_type cho phép NULL (= chưa xác định). Đơn tạo ở HĐ20 có payment_type NULL; hóa đơn đầu tiên (HĐ22 – HĐ23) ghi Full hoặc Installment cho đơn. CHECK vẫn chỉ nhận Full / Installment khi cột có giá trị.
- 17/10/2026: hóa đơn chuyển khoản không có kế toán (accountant_id NULL, có CHECK trong odin.sql). Hóa đơn chuyển khoản lệch do kế toán xác nhận thủ công (HĐ29) cũng để accountant_id NULL; người xác nhận được ghi vào log thanh toán.
- 18/10/2026: chức năng Tạo báo giá chỉ hiển thị khi cơ hội đủ điều kiện (HĐ2).
- 18/10/2026: kiểm tra hồ sơ khách hàng (email) chuyển lên bước lập báo giá (HĐ5 – HĐ6), không còn bước kiểm tra riêng khi tạo hóa đơn.
- 18/10/2026: voucher được giữ theo ngày lập báo giá; khi xác nhận không kiểm tra lại voucher (HĐ18 chỉ kiểm tra hạn báo giá).
- 18/10/2026: chuyển khoản lệch số tiền → báo lỗi cho kế toán và SP, kế toán kiểm tra sao kê và xác nhận thủ công khi khách đã chuyển đủ (HĐ28 – HĐ30). Không thêm cột vào CSDL, không cộng dồn giao dịch lệch; chuyển thừa và hoàn tiền nằm ngoài phạm vi.
- 18/10/2026: tiền mặt không có nhánh "không duyệt"; kế toán nhận tiền và xác nhận (HĐ32).
- 22/10/2026: khách trở thành học viên ở lần thanh toán thành công đầu tiên (HĐ35), không chờ đơn Hoàn tất; dữ liệu mẫu odin.sql / odin_demo_bulk.sql đã bổ sung học viên cho 12 đơn trả góp đã đóng tiền (STD145 – STD156).
- 22/10/2026: lập báo giá bắt đầu ở giai đoạn 1 và giai đoạn 3 giống quy trình xử lý cơ hội (Hải Linh); chọn Tạo báo giá thì cơ hội chuyển sang Chốt (HĐ4). Bỏ lane Khách hàng vì đây là quy trình nội bộ.
- 23/10/2026: khách không đồng ý báo giá thì SP từ chối và quy trình kết thúc (cơ hội đang xử lý → Lost, đã Won giữ nguyên; HĐ15 – HĐ16); khách muốn đổi khóa học / số lượng thì sửa báo giá Nháp (HĐ14). Bỏ bước hỏi "khách còn nhu cầu không".
- 25/10/2026: báo giá quá hạn không tự đổi trạng thái; hệ thống nhắc SP mỗi ngày, SP gia hạn bằng chỉnh sửa hoặc từ chối. Báo giá Nháp đã quá hạn vẫn được chỉnh sửa. Xác nhận báo giá quá hạn thì chặn và nhắc gia hạn (HĐ18), không tự chuyển Lost. Bỏ nhãn "Phân vân" của báo giá và bước tự từ chối báo giá quá hạn.
- 25/10/2026: một cơ hội vẫn có một hoặc nhiều báo giá; cơ hội ở Chốt (đang xử lý hoặc Won, không còn báo giá Nháp) lập thêm được báo giá, ví dụ học viên đăng ký thêm khóa học.
- 25/10/2026: tạo hóa đơn từ Quản lý đơn hàng; Trả góp mặc định số tiền bằng số còn lại; sửa hóa đơn không đổi được phương thức thanh toán; xuất PDF được cả báo giá quá hạn và báo giá Đã xác nhận. Quy trình còn 37 hoạt động.
- 25/10/2026: thêm trang chi tiết đơn hàng (chỉ xem; thông tin đơn, khóa học, lịch sử các kỳ hóa đơn; Salesperson lập báo giá tạo hóa đơn được từ trang này). Trong khóa luận, kế toán xác nhận tiền mặt và xác nhận chuyển khoản lệch được gộp thành một chức năng "Xác nhận thanh toán" (mỗi hóa đơn chỉ hiện một nút xác nhận theo phương thức). Quy trình vẫn 37 hoạt động.
- 27/10/2026: HĐ5 – HĐ10 sắp lại theo code: tổng tiền tự tính khi chọn khóa học, số lượng, voucher (HĐ6); SP chọn Lưu (HĐ7) rồi hệ thống mới kiểm tra (HĐ8), lỗi thì báo (HĐ9), hợp lệ thì lưu Nháp (HĐ10). Số hoạt động không đổi.
- 27/10/2026: tách bước nhập số tiền hóa đơn theo hình thức: Toàn bộ → hệ thống tự điền số còn được lập, không cho sửa (HĐ24); Trả góp → SP nhập số tiền kỳ này (HĐ25); SP nhập hạn, phương thức, chọn Tạo (HĐ26). Quy trình thành 39 hoạt động; từ HĐ24 cũ trở đi đánh lại +2. Các mục nhật ký trước ngày này dùng số HĐ cũ.
- 26/10/2026: khách chưa phản hồi báo giá thì chờ phản hồi hoặc chờ báo giá quá hạn (gateway sự kiện trong BPMN); quá hạn mà không phản hồi thì SP từ chối. Từ chối báo giá có chọn lý do: khách không đồng ý → Lost; khách không phản hồi đến khi quá hạn → Archived (cùng quy tắc "không liên lạc được" của quy trình xử lý cơ hội). Quy trình vẫn 37 hoạt động.
