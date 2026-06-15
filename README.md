# メリクレット | melikret OFFICIAL WEBSITE

札幌発・オルタナティブロックバンド「メリクレット」の公式サイト（静的HTML）。
サーバー不要。`index.html` をダブルクリックすればブラウザで開けます。

## ファイル構成

```
メリクレット/
├─ index.html        … 本体（ここの文言・情報を編集）
├─ css/style.css     … デザイン（色・余白・フォント・波紋）
├─ js/main.js        … 動き（水たまり波紋・スクロール演出・メニュー）
├─ images/           … サイトで表示する Web用画像（最適化済み）
├─ image/            … 元画像の保管用（大きい元データ。サイトでは未使用）
└─ README.md         … このファイル
```

## カラー

| 役割 | カラーコード | CSS変数 |
|------|------------|---------|
| メインカラー（スカイブルー） | `#87ceeb` | `--color-main` |
| サブカラー（藍緑） | `#5f9ea0` | `--color-sub` |

白基調のライトテーマです。色を変えたいときは `css/style.css` 冒頭の `:root { … }` を編集してください。

## 画像について（重要）

`image/` に置かれた元画像を、Web表示用に軽量化して `images/` に書き出しています（元の `.tif` はブラウザで表示できず、`.png` は数十MBあるため）。サイトが読むのは `images/` 側です。

| 用途 | 表示ファイル | 元ファイル |
|------|------------|-----------|
| ヒーロー（トップ大画像） | `images/hero.jpg` | `メインアー写.png` |
| プロフィール写真 | `images/profile.jpg` | `サブアー写.jpg` |
| メンバー ちぺ | `images/member-tipe.jpg` | `tipe.tif` |
| メンバー レニア | `images/member-renia.jpg` | `renia.tif` |
| メンバー 斗輝 | `images/member-toki.jpg` | `toki.tif` |
| 世界観イラスト | `images/illust.jpg` | `illust.png` |
| ロゴ（文字） | `images/logo_text_trim.png` | `logo_text.png` |
| ロゴ（雲マーク） | `images/logo_trim.png` | `logo.png` |

- **写真を差し替える**: 同じファイル名で `images/` の画像を上書きすればOK（縦横比は近いものを推奨）。
- メンバーは ちぺ / レニア / 斗輝 の3名構成です。

## 掲載データの出典（差し替え時の参照先）

| 項目 | 反映内容 | 出典 |
|------|---------|------|
| LIVE | 今後の公演15件 | [sonicon.jp/artists/melikret](https://sonicon.jp/artists/melikret)（時刻は表記が不正確だったため非掲載） |
| DISCOGRAPHY | 最新6作品。ジャケットはApple Musicの公式アートワークを参照（`mzstatic.com`） | iTunes API / [melikret.lnk.to](https://melikret.lnk.to/melikret) |
| MOVIE | YouTube @melikret のMV2本（ネガ・リセット！/ セミロング） | [youtube.com/@melikret](https://www.youtube.com/@melikret) |
| SNS | すべて `@melikret`（X / Instagram / YouTube / TikTok） | — |
| CONTACT | melikret.official@gmail.com | — |

## 中身の差し替え方（よく使うところ）

`index.html` を開くと、編集する場所を日本語コメント（`▼▼ … ▲▲`）で囲っています。

- **NEWS** … `▼▼ ニュース ▼▼` の `<li>` を複製・編集して増減
- **LIVE** … `▼▼ ライブ ▼▼` の `<li>` を複製・編集。`live__status` が「販売中」バッジ。終了公演は `class="live__item live__item--past"` を付けると薄く表示
- **DISCOGRAPHY** … `▼▼ ディスコグラフィー ▼▼` の `<li>` を複製。ジャケットの `<img src>` は Apple のアートワークURL（`/600x600bb.jpg`）。ローカルに保存したい場合は画像を `images/` に置いて差し替え
- **MEMBER** … メンバー名・パート・写真を編集
- **MOVIE** … `<iframe>` の `embed/動画ID` を別の動画IDに変えれば差し替え可能
- **SNS / メール** … 現在はすべて `@melikret` と `melikret.official@gmail.com` を設定済み。変更時はヘッダー・CONTACT・フッターの該当 `href` を編集

## 公開（アップロード）

`メリクレット/` フォルダの中身一式（`images/` を含む）を、レンタルサーバーの公開フォルダ（`public_html` など）へ
そのままアップロードすれば公開できます。トップページは `index.html` です。
元データ保管用の `image/` フォルダはアップロード不要です（容量が大きいため除外して構いません）。
