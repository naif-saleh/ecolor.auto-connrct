using Newtonsoft.Json.Linq;
using System.Diagnostics;

public class GetCounterClass
    {
    public int GetCount(string GetDataResponse)
        {
        var data = JObject.Parse(GetDataResponse);
        return (int)data["auto_call"];
        }
    }


