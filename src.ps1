﻿git ls-tree -r HEAD --name-only benchmark memprof td tdactor tddb tdnet tdtl tdutils test tg_http_client | Select-String "\.cpp$|\.h$|\.hpp$" | Select-String -NotMatch "third_party"
