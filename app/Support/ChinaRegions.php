<?php

namespace App\Support;

class ChinaRegions
{
    /**
     * @return array<string, string>
     */
    public static function provinceOptions(): array
    {
        return collect(self::provinces())->mapWithKeys(fn (string $province): array => [$province => $province])->all();
    }

    /**
     * @return array<string, array<string, array<string, array<int, string>>>>
     */
    private static function fullRegionTree(): array
    {
        static $tree = null;

        if (is_array($tree)) {
            return $tree;
        }

        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'china-pcas.json';

        if (! is_file($path)) {
            return $tree = [];
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            return $tree = [];
        }

        $tree = [];

        foreach ($rows as $provinceName => $cities) {
            if (! is_string($provinceName) || ! is_array($cities)) {
                continue;
            }

            $provinceKey = self::shortProvinceName($provinceName);

            foreach ($cities as $cityName => $districts) {
                if (! is_string($cityName) || ! is_array($districts)) {
                    continue;
                }

                $cityKey = in_array($provinceKey, ['北京', '天津', '上海', '重庆'], true) && $cityName === '市辖区'
                    ? $provinceKey.'市'
                    : $cityName;

                foreach ($districts as $districtName => $streets) {
                    if (! is_string($districtName)) {
                        continue;
                    }

                    $tree[$provinceKey][$cityKey][$districtName] = collect(is_array($streets) ? $streets : [])
                        ->filter()
                        ->values()
                        ->all();
                }

                $tree[$provinceKey][$cityKey] ??= [];
            }
        }

        return $tree;
    }

    private static function shortProvinceName(string $name): string
    {
        $replacements = [
            '北京市' => '北京',
            '天津市' => '天津',
            '上海市' => '上海',
            '重庆市' => '重庆',
            '广西壮族自治区' => '广西',
            '内蒙古自治区' => '内蒙古',
            '宁夏回族自治区' => '宁夏',
            '新疆维吾尔自治区' => '新疆',
            '西藏自治区' => '西藏',
            '香港特别行政区' => '香港',
            '澳门特别行政区' => '澳门',
        ];

        if (isset($replacements[$name])) {
            return $replacements[$name];
        }

        return preg_replace('/(省|市)$/u', '', $name) ?: $name;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function cityOptionsByProvince(): array
    {
        return [
            '北京' => ['北京市'],
            '天津' => ['天津市'],
            '上海' => ['上海市'],
            '重庆' => ['重庆市'],
            '河北' => ['石家庄市', '唐山市', '秦皇岛市', '邯郸市', '邢台市', '保定市', '张家口市', '承德市', '沧州市', '廊坊市', '衡水市'],
            '山西' => ['太原市', '大同市', '阳泉市', '长治市', '晋城市', '朔州市', '晋中市', '运城市', '忻州市', '临汾市', '吕梁市'],
            '内蒙古' => ['呼和浩特市', '包头市', '乌海市', '赤峰市', '通辽市', '鄂尔多斯市', '呼伦贝尔市', '巴彦淖尔市', '乌兰察布市', '兴安盟', '锡林郭勒盟', '阿拉善盟'],
            '辽宁' => ['沈阳市', '大连市', '鞍山市', '抚顺市', '本溪市', '丹东市', '锦州市', '营口市', '阜新市', '辽阳市', '盘锦市', '铁岭市', '朝阳市', '葫芦岛市'],
            '吉林' => ['长春市', '吉林市', '四平市', '辽源市', '通化市', '白山市', '松原市', '白城市', '延边朝鲜族自治州'],
            '黑龙江' => ['哈尔滨市', '齐齐哈尔市', '鸡西市', '鹤岗市', '双鸭山市', '大庆市', '伊春市', '佳木斯市', '七台河市', '牡丹江市', '黑河市', '绥化市', '大兴安岭地区'],
            '江苏' => ['南京市', '无锡市', '徐州市', '常州市', '苏州市', '南通市', '连云港市', '淮安市', '盐城市', '扬州市', '镇江市', '泰州市', '宿迁市'],
            '浙江' => ['杭州市', '宁波市', '温州市', '嘉兴市', '湖州市', '绍兴市', '金华市', '衢州市', '舟山市', '台州市', '丽水市'],
            '安徽' => ['合肥市', '芜湖市', '蚌埠市', '淮南市', '马鞍山市', '淮北市', '铜陵市', '安庆市', '黄山市', '滁州市', '阜阳市', '宿州市', '六安市', '亳州市', '池州市', '宣城市'],
            '福建' => ['福州市', '厦门市', '莆田市', '三明市', '泉州市', '漳州市', '南平市', '龙岩市', '宁德市'],
            '江西' => ['南昌市', '景德镇市', '萍乡市', '九江市', '新余市', '鹰潭市', '赣州市', '吉安市', '宜春市', '抚州市', '上饶市'],
            '山东' => ['济南市', '青岛市', '淄博市', '枣庄市', '东营市', '烟台市', '潍坊市', '济宁市', '泰安市', '威海市', '日照市', '临沂市', '德州市', '聊城市', '滨州市', '菏泽市'],
            '河南' => ['郑州市', '开封市', '洛阳市', '平顶山市', '安阳市', '鹤壁市', '新乡市', '焦作市', '濮阳市', '许昌市', '漯河市', '三门峡市', '南阳市', '商丘市', '信阳市', '周口市', '驻马店市', '济源市'],
            '湖北' => ['武汉市', '黄石市', '十堰市', '宜昌市', '襄阳市', '鄂州市', '荆门市', '孝感市', '荆州市', '黄冈市', '咸宁市', '随州市', '恩施土家族苗族自治州', '仙桃市', '潜江市', '天门市', '神农架林区'],
            '湖南' => ['长沙市', '株洲市', '湘潭市', '衡阳市', '邵阳市', '岳阳市', '常德市', '张家界市', '益阳市', '郴州市', '永州市', '怀化市', '娄底市', '湘西土家族苗族自治州'],
            '广东' => ['广州市', '韶关市', '深圳市', '珠海市', '汕头市', '佛山市', '江门市', '湛江市', '茂名市', '肇庆市', '惠州市', '梅州市', '汕尾市', '河源市', '阳江市', '清远市', '东莞市', '中山市', '潮州市', '揭阳市', '云浮市'],
            '广西' => ['南宁市', '柳州市', '桂林市', '梧州市', '北海市', '防城港市', '钦州市', '贵港市', '玉林市', '百色市', '贺州市', '河池市', '来宾市', '崇左市'],
            '海南' => ['海口市', '三亚市', '三沙市', '儋州市', '五指山市', '琼海市', '文昌市', '万宁市', '东方市', '定安县', '屯昌县', '澄迈县', '临高县', '白沙黎族自治县', '昌江黎族自治县', '乐东黎族自治县', '陵水黎族自治县', '保亭黎族苗族自治县', '琼中黎族苗族自治县'],
            '四川' => ['成都市', '自贡市', '攀枝花市', '泸州市', '德阳市', '绵阳市', '广元市', '遂宁市', '内江市', '乐山市', '南充市', '眉山市', '宜宾市', '广安市', '达州市', '雅安市', '巴中市', '资阳市', '阿坝藏族羌族自治州', '甘孜藏族自治州', '凉山彝族自治州'],
            '贵州' => ['贵阳市', '六盘水市', '遵义市', '安顺市', '毕节市', '铜仁市', '黔西南布依族苗族自治州', '黔东南苗族侗族自治州', '黔南布依族苗族自治州'],
            '云南' => ['昆明市', '曲靖市', '玉溪市', '保山市', '昭通市', '丽江市', '普洱市', '临沧市', '楚雄彝族自治州', '红河哈尼族彝族自治州', '文山壮族苗族自治州', '西双版纳傣族自治州', '大理白族自治州', '德宏傣族景颇族自治州', '怒江傈僳族自治州', '迪庆藏族自治州'],
            '西藏' => ['拉萨市', '日喀则市', '昌都市', '林芝市', '山南市', '那曲市', '阿里地区'],
            '陕西' => ['西安市', '铜川市', '宝鸡市', '咸阳市', '渭南市', '延安市', '汉中市', '榆林市', '安康市', '商洛市'],
            '甘肃' => ['兰州市', '嘉峪关市', '金昌市', '白银市', '天水市', '武威市', '张掖市', '平凉市', '酒泉市', '庆阳市', '定西市', '陇南市', '临夏回族自治州', '甘南藏族自治州'],
            '青海' => ['西宁市', '海东市', '海北藏族自治州', '黄南藏族自治州', '海南藏族自治州', '果洛藏族自治州', '玉树藏族自治州', '海西蒙古族藏族自治州'],
            '宁夏' => ['银川市', '石嘴山市', '吴忠市', '固原市', '中卫市'],
            '新疆' => ['乌鲁木齐市', '克拉玛依市', '吐鲁番市', '哈密市', '昌吉回族自治州', '博尔塔拉蒙古自治州', '巴音郭楞蒙古自治州', '阿克苏地区', '克孜勒苏柯尔克孜自治州', '喀什地区', '和田地区', '伊犁哈萨克自治州', '塔城地区', '阿勒泰地区', '石河子市', '阿拉尔市', '图木舒克市', '五家渠市', '北屯市', '铁门关市', '双河市', '可克达拉市', '昆玉市', '胡杨河市', '新星市', '白杨市'],
            '香港' => ['香港特别行政区'],
            '澳门' => ['澳门特别行政区'],
            '台湾' => ['台北市', '新北市', '桃园市', '台中市', '台南市', '高雄市', '基隆市', '新竹市', '嘉义市', '新竹县', '苗栗县', '彰化县', '南投县', '云林县', '嘉义县', '屏东县', '宜兰县', '花莲县', '台东县', '澎湖县', '金门县', '连江县'],
        ];
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    public static function regionTreeForForms(): array
    {
        $fullTree = self::fullRegionTree();

        if ($fullTree !== []) {
            foreach (self::regionTree() as $province => $cities) {
                foreach ($cities as $city => $districts) {
                    $fullTree[$province][$city] ??= $districts;
                }
            }

            return $fullTree;
        }

        $tree = self::regionTree();

        foreach (self::cityOptionsByProvince() as $province => $cities) {
            foreach ($cities as $city) {
                $tree[$province][$city] ??= [];
            }
        }

        return $tree;
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    public static function regionTree(): array
    {
        return [
            '北京' => ['北京市' => ['东城区', '西城区', '朝阳区', '海淀区', '丰台区', '石景山区', '通州区', '昌平区', '大兴区', '顺义区', '房山区', '门头沟区', '怀柔区', '平谷区', '密云区', '延庆区']],
            '天津' => ['天津市' => ['和平区', '河东区', '河西区', '南开区', '河北区', '红桥区', '滨海新区', '东丽区', '西青区', '津南区', '北辰区', '武清区', '宝坻区', '静海区', '宁河区', '蓟州区']],
            '上海' => ['上海市' => ['黄浦区', '徐汇区', '长宁区', '静安区', '普陀区', '虹口区', '杨浦区', '浦东新区', '闵行区', '宝山区', '嘉定区', '金山区', '松江区', '青浦区', '奉贤区', '崇明区']],
            '重庆' => ['重庆市' => ['渝中区', '江北区', '南岸区', '九龙坡区', '沙坪坝区', '大渡口区', '渝北区', '巴南区', '北碚区', '万州区', '涪陵区', '长寿区', '江津区', '合川区', '永川区', '南川区', '綦江区', '大足区', '璧山区', '铜梁区', '潼南区', '荣昌区', '开州区', '梁平区', '武隆区']],
            '河北' => ['石家庄市' => ['长安区', '桥西区', '新华区', '裕华区'], '唐山市' => ['路南区', '路北区', '开平区'], '秦皇岛市' => ['海港区', '山海关区', '北戴河区'], '保定市' => ['竞秀区', '莲池区', '满城区'], '廊坊市' => ['安次区', '广阳区']],
            '山西' => ['太原市' => ['小店区', '迎泽区', '杏花岭区'], '大同市' => ['平城区', '云冈区'], '晋中市' => ['榆次区']],
            '内蒙古' => ['呼和浩特市' => ['新城区', '回民区', '玉泉区', '赛罕区'], '包头市' => ['昆都仑区', '青山区', '东河区'], '赤峰市' => ['红山区', '松山区']],
            '辽宁' => ['沈阳市' => ['和平区', '沈河区', '铁西区', '皇姑区'], '大连市' => ['中山区', '西岗区', '沙河口区', '甘井子区']],
            '吉林' => ['长春市' => ['南关区', '宽城区', '朝阳区', '二道区'], '吉林市' => ['昌邑区', '船营区', '龙潭区']],
            '黑龙江' => ['哈尔滨市' => ['道里区', '南岗区', '道外区', '香坊区'], '齐齐哈尔市' => ['龙沙区', '建华区'], '大庆市' => ['萨尔图区', '龙凤区']],
            '江苏' => ['南京市' => ['玄武区', '秦淮区', '建邺区', '鼓楼区'], '苏州市' => ['姑苏区', '虎丘区', '吴中区', '相城区'], '无锡市' => ['梁溪区', '滨湖区', '锡山区'], '常州市' => ['天宁区', '钟楼区', '新北区']],
            '浙江' => ['杭州市' => ['上城区', '拱墅区', '西湖区', '滨江区', '萧山区'], '宁波市' => ['海曙区', '江北区', '北仑区'], '温州市' => ['鹿城区', '龙湾区', '瓯海区']],
            '安徽' => ['合肥市' => ['瑶海区', '庐阳区', '蜀山区', '包河区'], '芜湖市' => ['镜湖区', '弋江区'], '蚌埠市' => ['龙子湖区', '蚌山区']],
            '福建' => ['福州市' => ['鼓楼区', '台江区', '仓山区'], '厦门市' => ['思明区', '海沧区', '湖里区'], '泉州市' => ['鲤城区', '丰泽区']],
            '江西' => ['南昌市' => ['东湖区', '西湖区', '青云谱区'], '九江市' => ['浔阳区', '濂溪区'], '赣州市' => ['章贡区', '南康区']],
            '山东' => ['济南市' => ['历下区', '市中区', '槐荫区'], '青岛市' => ['市南区', '市北区', '李沧区'], '烟台市' => ['芝罘区', '福山区']],
            '河南' => ['郑州市' => ['中原区', '二七区', '金水区'], '洛阳市' => ['老城区', '西工区', '涧西区'], '开封市' => ['龙亭区', '鼓楼区']],
            '湖北' => ['武汉市' => ['江岸区', '江汉区', '硚口区', '武昌区', '洪山区'], '宜昌市' => ['西陵区', '伍家岗区'], '襄阳市' => ['襄城区', '樊城区']],
            '湖南' => ['长沙市' => ['芙蓉区', '天心区', '岳麓区', '开福区'], '株洲市' => ['荷塘区', '芦淞区'], '湘潭市' => ['雨湖区', '岳塘区']],
            '广东' => ['广州市' => ['越秀区', '海珠区', '荔湾区', '天河区', '白云区'], '深圳市' => ['福田区', '罗湖区', '南山区', '宝安区', '龙岗区'], '珠海市' => ['香洲区', '斗门区'], '东莞市' => ['东城街道', '南城街道', '莞城街道']],
            '广西' => ['南宁市' => ['兴宁区', '青秀区', '江南区'], '桂林市' => ['秀峰区', '叠彩区', '象山区'], '柳州市' => ['城中区', '鱼峰区']],
            '海南' => ['海口市' => ['秀英区', '龙华区', '琼山区'], '三亚市' => ['海棠区', '吉阳区', '天涯区'], '儋州市' => ['那大镇']],
            '四川' => ['成都市' => ['锦江区', '青羊区', '金牛区', '武侯区', '成华区'], '绵阳市' => ['涪城区', '游仙区'], '德阳市' => ['旌阳区', '罗江区']],
            '贵州' => ['贵阳市' => ['南明区', '云岩区', '花溪区'], '遵义市' => ['红花岗区', '汇川区'], '毕节市' => ['七星关区']],
            '云南' => ['昆明市' => ['五华区', '盘龙区', '官渡区'], '大理白族自治州' => ['大理市'], '丽江市' => ['古城区']],
            '西藏' => ['拉萨市' => ['城关区', '堆龙德庆区'], '日喀则市' => ['桑珠孜区'], '林芝市' => ['巴宜区']],
            '陕西' => ['西安市' => ['新城区', '碑林区', '莲湖区', '雁塔区'], '咸阳市' => ['秦都区', '渭城区'], '宝鸡市' => ['渭滨区', '金台区']],
            '甘肃' => ['兰州市' => ['城关区', '七里河区', '西固区'], '天水市' => ['秦州区', '麦积区'], '酒泉市' => ['肃州区']],
            '青海' => ['西宁市' => ['城东区', '城中区', '城西区'], '海东市' => ['乐都区', '平安区']],
            '宁夏' => ['银川市' => ['兴庆区', '西夏区', '金凤区'], '吴忠市' => ['利通区'], '石嘴山市' => ['大武口区']],
            '新疆' => ['乌鲁木齐市' => ['天山区', '沙依巴克区', '新市区'], '克拉玛依市' => ['克拉玛依区'], '喀什地区' => ['喀什市']],
            '香港' => ['香港特别行政区' => ['中西区', '湾仔区', '东区', '南区', '油尖旺区', '深水埗区', '九龙城区', '黄大仙区', '观塘区', '葵青区', '荃湾区', '屯门区', '元朗区', '北区', '大埔区', '沙田区', '西贡区', '离岛区']],
            '澳门' => ['澳门特别行政区' => ['花地玛堂区', '圣安多尼堂区', '大堂区', '望德堂区', '风顺堂区', '嘉模堂区', '圣方济各堂区']],
            '台湾' => ['台北市' => ['中正区', '大同区', '中山区', '松山区'], '新北市' => ['板桥区', '三重区', '中和区'], '高雄市' => ['新兴区', '前金区', '苓雅区'], '台中市' => ['中区', '东区', '南区']],
        ];
    }

    public static function presetOptions(): array
    {
        return [
            'northwest' => '西北地区',
            'xinjiang_tibet' => '新疆西藏地区',
            'northeast' => '东北地区',
            'inland' => '内陆地区',
            'east' => '华东地区',
            'south' => '华南地区',
            'southwest' => '西南地区',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function provincesForPreset(?string $preset): array
    {
        return match ($preset) {
            'northwest' => ['陕西', '甘肃', '宁夏', '青海', '内蒙古'],
            'xinjiang_tibet' => ['新疆', '西藏'],
            'northeast' => ['黑龙江', '吉林', '辽宁'],
            'inland' => ['河南', '湖北', '湖南', '江西', '安徽', '山西'],
            'east' => ['上海', '江苏', '浙江', '山东', '福建', '台湾'],
            'south' => ['广东', '广西', '海南', '香港', '澳门'],
            'southwest' => ['重庆', '四川', '贵州', '云南'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function provinces(): array
    {
        return array_keys(self::regionTree());
    }

    public static function normalizeProvince(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (self::provinces() as $province) {
            if ($value === $province || str_contains($value, $province)) {
                return $province;
            }
        }

        $normalized = preg_replace('/(省|市|自治区|壮族|回族|维吾尔|特别行政区)$/u', '', $value);
        $normalized = trim((string) $normalized);

        return in_array($normalized, self::provinces(), true) ? $normalized : $value;
    }

    public static function guessProvinceFromAddress(?string $address): ?string
    {
        return self::parseAddress((string) $address)['province'] ?? null;
    }

    /**
     * @return array{country:string, province?:string, city?:string, district?:string, street?:string, detail?:string}
     */
    public static function parseAddress(string $raw): array
    {
        $text = preg_replace('/\s+/', '', trim($raw)) ?: trim($raw);
        $parsed = ['country' => str_contains($text, '中国') ? '中国' : '中国'];
        $text = preg_replace('/^中国/u', '', $text) ?: $text;

        foreach (self::regionTreeForForms() as $province => $cities) {
            if (! str_starts_with($text, $province) && ! str_contains($text, $province)) {
                continue;
            }

            $provinceText = in_array($province, ['北京', '天津', '上海', '重庆'], true) && str_contains($text, $province.'市')
                ? $province.'市'
                : $province;
            $parsed['province'] = $provinceText;
            $text = self::removeFirst($text, $provinceText);
            foreach (['省', '市', '壮族自治区', '回族自治区', '维吾尔自治区', '自治区', '特别行政区'] as $suffix) {
                if (str_starts_with($text, $suffix)) {
                    $text = mb_substr($text, mb_strlen($suffix));
                    break;
                }
            }

            foreach ($cities as $city => $districts) {
                if (! str_starts_with($text, $city) && ! str_contains($text, $city)) {
                    continue;
                }

                $parsed['city'] = $city;
                $text = self::removeFirst($text, $city);
                break;
            }

            if (! isset($parsed['city']) && count($cities) === 1) {
                $parsed['city'] = array_key_first($cities);
            }

            $districts = $cities[$parsed['city'] ?? ''] ?? [];
            $districtNames = array_is_list($districts) ? $districts : array_keys($districts);

            foreach ($districtNames as $district) {
                if (! str_starts_with($text, $district) && ! str_contains($text, $district)) {
                    continue;
                }

                $parsed['district'] = $district;
                $text = self::removeFirst($text, $district);
                break;
            }

            $streets = isset($parsed['district']) && ! array_is_list($districts)
                ? ($districts[$parsed['district']] ?? [])
                : [];

            foreach ($streets as $street) {
                if (! str_starts_with($text, $street) && ! str_contains($text, $street)) {
                    continue;
                }

                $parsed['street'] = $street;
                $text = self::removeFirst($text, $street);
                break;
            }

            break;
        }

        if (! isset($parsed['province'])) {
            $matchedProvince = null;
            if (preg_match('/(?P<province>[^省市区县]{2,}(?:省|市|自治区|特别行政区))/u', $text, $match)) {
                $matchedProvince = self::normalizeProvince($match['province']);
                $parsed['province'] = $matchedProvince;
                $text = self::removeFirst($text, $match['province']);
            }
        }

        if (! isset($parsed['city']) && preg_match('/(?P<city>[^省市区县]{2,}(?:市|自治州|地区|盟))/u', $text, $match)) {
            $parsed['city'] = $match['city'];
            $text = self::removeFirst($text, $match['city']);
        }

        if (! isset($parsed['district']) && preg_match('/(?P<district>[^省市区县]{2,}(?:区|县|旗|市))/u', $text, $match)) {
            $parsed['district'] = $match['district'];
            $text = self::removeFirst($text, $match['district']);
        }

        $text = trim($text);
        if ($text !== '') {
            $parsed['detail'] = $text;
            $parsed['street'] ??= $text;
        }

        return $parsed;
    }

    private static function removeFirst(string $text, string $needle): string
    {
        $position = mb_strpos($text, $needle);

        if ($position === false) {
            return $text;
        }

        return mb_substr($text, 0, $position).mb_substr($text, $position + mb_strlen($needle));
    }
}
