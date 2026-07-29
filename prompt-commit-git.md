# Prompt: Commit code

Hãy commit các thay đổi hiện tại theo quy tắc sau:

1. Chạy `git status` + `git diff` để nắm rõ những gì đã đổi. **Chỉ commit khi các thay đổi đã rõ ràng, hoàn chỉnh và không lẫn file rác/nháp.** Nếu còn mơ hồ thì dừng và hỏi lại.
2. Nhóm thay đổi hợp lý; nếu lẫn nhiều mục đích khác nhau thì tách thành nhiều commit.
3. Viết commit message theo Conventional Commits: `type(scope): mô tả ngắn` (feat, fix, refactor, chore, docs, perf...). Phần thân liệt kê ý chính bằng gạch đầu dòng. **Toàn bộ message tối đa 100 từ, viết bằng tiếng Việt.**
4. Cập nhật `changelog.md`: thêm mục mới ở đầu file với ngày (YYYY-MM-DD) và các ý chính vừa thay đổi để dễ theo dõi.
5. `git add` các file liên quan (kể cả `changelog.md`) rồi tạo commit. Không tự `push` trừ khi được yêu cầu.
